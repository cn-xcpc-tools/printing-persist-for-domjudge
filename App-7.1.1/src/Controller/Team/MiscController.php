<?php declare(strict_types=1);

namespace App\Controller\Team;

use App\Controller\BaseController;
use App\Entity\Clarification;
use App\Entity\Language;
use App\Form\Type\PrintType;
use App\Service\DOMJudgeService;
use App\Service\PrintingService;
use App\Service\ScoreboardService;
use App\Service\SubmissionService;
use App\Utils\Printing;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class MiscController
 *
 * @Route("/team")
 * @IsGranted("ROLE_TEAM")
 * @Security("user.getTeam() !== null", message="You do not have a team associated with your account. ")
 *
 * @package App\Controller\Team
 */
class MiscController extends BaseController
{
    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var ScoreboardService
     */
    protected $scoreboardService;

    /**
     * @var SubmissionService
     */
    protected $submissionService;

    /**
     * @var PrintingService
     */
    protected $ps;

    /**
     * MiscController constructor.
     * @param DOMJudgeService        $dj
     * @param EntityManagerInterface $em
     * @param ScoreboardService      $scoreboardService
     * @param SubmissionService      $submissionService
     * @param PrintingService        $ps
     */
    public function __construct(
        DOMJudgeService $dj,
        EntityManagerInterface $em,
        ScoreboardService $scoreboardService,
        SubmissionService $submissionService,
        PrintingService $ps
    ) {
        $this->dj                = $dj;
        $this->em                = $em;
        $this->scoreboardService = $scoreboardService;
        $this->submissionService = $submissionService;
        $this->ps                = $ps;
    }

    /**
     * @Route("", name="team_index")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function homeAction(Request $request)
    {
        $user    = $this->dj->getUser();
        $team    = $user->getTeam();
        $teamId  = $team->getTeamid();
        $contest = $this->dj->getCurrentContest($teamId);

        $data = [
            'team' => $team,
            'contest' => $contest,
            'refresh' => [
                'after' => 30,
                'url' => $this->generateUrl('team_index'),
                'ajax' => true,
            ],
            'maxWidth' => $this->dj->dbconfig_get('team_column_width', 0),
        ];
        if ($contest) {
            $data['scoreboard']           = $this->scoreboardService->getTeamScoreboard($contest, $teamId, true);
            $data['showFlags']            = $this->dj->dbconfig_get('show_flags', true);
            $data['showAffiliationLogos'] = $this->dj->dbconfig_get('show_affiliation_logos', false);
            $data['showAffiliations']     = $this->dj->dbconfig_get('show_affiliations', true);
            $data['showPending']          = $this->dj->dbconfig_get('show_pending', false);
            $data['showTeamSubmissions']  = $this->dj->dbconfig_get('show_teams_submissions', true);
            $data['scoreInSeconds']       = $this->dj->dbconfig_get('score_in_seconds', false);
            $data['verificationRequired'] = $this->dj->dbconfig_get('verification_required', false);
            $data['limitToTeams']         = [$team];
            // We need to clear the entity manager, because loading the team scoreboard seems to break getting submission
            // contestproblems for the contest we get the scoreboard for
            $this->em->clear();
            $data['submissions'] = $this->submissionService->getSubmissionList([$contest->getCid() => $contest],
                                                                               ['teamid' => $teamId], 0)[0];

            /** @var Clarification[] $clarifications */
            $clarifications = $this->em->createQueryBuilder()
                ->from(Clarification::class, 'c')
                ->leftJoin('c.problem', 'p')
                ->leftJoin('c.sender', 's')
                ->leftJoin('c.recipient', 'r')
                ->select('c', 'p')
                ->andWhere('c.contest = :contest')
                ->andWhere('c.sender IS NULL')
                ->andWhere('c.recipient = :team OR c.recipient IS NULL')
                ->setParameter(':contest', $contest)
                ->setParameter(':team', $team)
                ->addOrderBy('c.submittime', 'DESC')
                ->addOrderBy('c.clarid', 'DESC')
                ->getQuery()
                ->getResult();

            /** @var Clarification[] $clarificationRequests */
            $clarificationRequests = $this->em->createQueryBuilder()
                ->from(Clarification::class, 'c')
                ->leftJoin('c.problem', 'p')
                ->leftJoin('c.sender', 's')
                ->leftJoin('c.recipient', 'r')
                ->select('c', 'p')
                ->andWhere('c.contest = :contest')
                ->andWhere('c.sender = :team')
                ->setParameter(':contest', $contest)
                ->setParameter(':team', $team)
                ->addOrderBy('c.submittime', 'DESC')
                ->addOrderBy('c.clarid', 'DESC')
                ->getQuery()
                ->getResult();

            $data['clarifications']        = $clarifications;
            $data['clarificationRequests'] = $clarificationRequests;
            $data['categories']            = $this->dj->dbconfig_get('clar_categories');
        }

        if ($request->isXmlHttpRequest()) {
            $data['ajax'] = true;
            return $this->render('team/partials/index_content.html.twig', $data);
        }

        return $this->render('team/index.html.twig', $data);
    }

    /**
     * @Route("/change-contest/{contestId<-?\d+>}", name="team_change_contest")
     * @param Request         $request
     * @param RouterInterface $router
     * @param int             $contestId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function changeContestAction(Request $request, RouterInterface $router, int $contestId)
    {
        if ($this->isLocalReferrer($router, $request)) {
            $response = new RedirectResponse($request->headers->get('referer'));
        } else {
            $response = $this->redirectToRoute('public_index');
        }
        return $this->dj->setCookie('domjudge_cid', (string)$contestId, 0, null, '', false, false,
                                                 $response);
    }

    /**
     * @Route("/print", name="team_print")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function printAction(Request $request)
    {
        if (!$this->dj->dbconfig_get('print_command', '')) {
            throw new AccessDeniedHttpException("Printing disabled in config");
        }

        $form = $this->createForm(PrintType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            /** @var UploadedFile $file */
            $file   = $data['code'];
            $langid = $data['langid'];
            $user   = $this->dj->getUser();
            $printg = $this->ps->submitPrint($user, $langid, $file, null, $msg);

            return $this->render('team/print_result.html.twig', [
                'success' => $printg != null,
                'output' => $msg,
            ]);
        }

        /** @var Language[] $languages */
        $languages = $this->em->createQueryBuilder()
            ->from(Language::class, 'l')
            ->select('l')
            ->andWhere('l.allowSubmit = 1')
            ->getQuery()
            ->getResult();

        return $this->render('team/print.html.twig', [
            'form' => $form->createView(),
            'languages' => $languages,
        ]);
    }
}
