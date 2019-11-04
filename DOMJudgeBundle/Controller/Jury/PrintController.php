<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Controller\BaseController;
use DOMJudgeBundle\Entity\Language;
use DOMJudgeBundle\Form\Type\PrintType;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\PrintingService;
use DOMJudgeBundle\Utils\Printing;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class PrintController
 *
 * @Route("/jury/print")
 * @Security("has_role('ROLE_JURY') or has_role('ROLE_BALLOON')")
 *
 * @package DOMJudgeBundle\Controller\Jury
 */
class PrintController extends BaseController
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var PrintingService
     */
    protected $ps;

    /**
     * PrintController constructor.
     * @param EntityManagerInterface $em
     * @param DOMJudgeService        $dj
     * @param PrintingService        $ps
     */
    public function __construct(EntityManagerInterface $em, DOMJudgeService $dj, PrintingService $ps)
    {
        $this->em = $em;
        $this->dj = $dj;
        $this->ps = $ps;
    }

    /**
     * @Route("", name="jury_print")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function showAction(Request $request)
    {
        if (!$this->dj->dbconfig_get('enable_printing', 0)) {
            throw new AccessDeniedHttpException("Printing disabled in config");
        }

        $form = $this->createForm(PrintType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            /** @var UploadedFile $file */
            $file   = $data['code'];
            $langid = $data['langid'];
            $user   = $this->getUser();
            $printg = $this->ps->submitPrint($user, $langid, $file, null, $msg);

            return $this->render('@DOMJudge/jury/print_result.html.twig', [
                'success' => $printg != null,
                'output' => $msg,
            ]);
        }

        /** @var Language[] $languages */
        $languages = $this->em->createQueryBuilder()
            ->from('DOMJudgeBundle:Language', 'l')
            ->select('l')
            ->andWhere('l.allowSubmit = 1')
            ->getQuery()
            ->getResult();

        return $this->render('@DOMJudge/jury/print.html.twig', [
            'form' => $form->createView(),
            'languages' => $languages,
        ]);
    }
}
