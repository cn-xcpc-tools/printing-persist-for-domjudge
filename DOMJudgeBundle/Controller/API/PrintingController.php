<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\API;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use DOMJudgeBundle\Entity\Prints;
use DOMJudgeBundle\Entity\PrintWithSourceCode;
use DOMJudgeBundle\Entity\User;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\EventLogService;
use DOMJudgeBundle\Utils\Utils;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\FOSRestController;
use Nelmio\ApiDocBundle\Annotation\Model;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Rest\Route("/api/v4/printing", defaults={ "_format" = "json" })
 * @Rest\Prefix("/api/printing")
 * @Rest\NamePrefix("printing_")
 * @SWG\Tag(name="Printings")
 * @SWG\Response(response="404", ref="#/definitions/NotFound")
 * @Security("has_role('ROLE_JURY') or has_role('ROLE_BALLOON')")
 */
class PrintingController extends FOSRestController
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
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * PrintingController constructor.
     * @param EntityManagerInterface $em
     * @param DOMJudgeService        $dj
     * @param EventLogService        $eventLogService
     * @param LoggerInterface        $logger
     */
    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        EventLogService $eventLogService,
        LoggerInterface $logger
    ) {
        $this->em                = $em;
        $this->dj                = $dj;
        $this->eventLogService   = $eventLogService;
        $this->logger            = $logger;
    }

    /**
     * Set the printing as resolved
     * @Rest\Post("/set-done/{printId}")
     * @SWG\Response(
     *     response="200",
     *     description="The basic information for this printing"
     * )
     * @param int $printId
     * @return array|string
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Exception
     */
    public function setDoneAction(int $printId) {
        /** @var Prints $print */
        $print = $this->em->getRepository(Prints::class)->find($printId);

        if (empty($print)) {
            throw new NotFoundHttpException("Printing not found");
        }

        $print->setDone(true);
        $print->setProcessed(true);
        $this->em->flush();

        $lang = $print->getLangid();
        if (empty($lang)) $lang = 'plain';
        $user = $print->getUser();
        $teamInfo = $user->getTeam();
        $team = $teamInfo == null
            ? "u" . $user->getUserid() . ": " . $user->getName()
            : "t" . $teamInfo->getTeamid() . ": " . $teamInfo->getName() . " (" . $teamInfo->getRoom() . ")";

        return [
            'id' => $print->getPrintid(),
            'time' => $print->getTime(),
            'filename' => $print->getFilename(),
            'lang' => $lang,
            'team' => $team,
            'processed' => true,
            'done' => true
        ];
    }

    /**
     * Get the next printing and mark as processed
     * @Rest\Post("/next-printing")
     * @SWG\Response(
     *     response="200",
     *     description="The next printing"
     * )
     * @return array|string
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Exception
     */
    public function getNextPrintingAction()
    {
        $query = $this->em->createQueryBuilder()
            ->select('b')
            ->from('DOMJudgeBundle:Prints', 'b')
            ->andWhere('b.processed = 0')
            ->orderBy('b.time', 'ASC');

        /** @var Prints[] $prints*/
        $prints = $query->getQuery()->getResult();

        $numUpdated = 0;

        // Pick first print
        foreach ($prints as $print) {
            // update exactly one print
            // Note: this might still return 0 if another printclient beat us to it
            // We do this directly as an SQL query so we can get the number of affected rows
            $numUpdated = $this->em->getConnection()->executeUpdate(
                'UPDATE print SET processed = 1 WHERE printid = :printid AND processed = 0',
                [
                    ':printid' => $print->getPrintid()
                ]
            );
            if ($numUpdated == 1) {
                break;
            }
        }

        // No printing can be claimed
        if (empty($print) || $numUpdated == 0) {
            return '';
        }

        /** @var PrintWithSourceCode $print */
        $print = $this->em->getRepository(PrintWithSourceCode::class)->find($print->getPrintid());

        $lang = $print->getLangid();
        if (empty($lang)) $lang = 'plain';
        $user = $print->getUser();
        $teamInfo = $user->getTeam();
        $team = $teamInfo == null
            ? "u" . $user->getUserid() . ": " . $user->getName()
            : "t" . $teamInfo->getTeamid() . ": " . $teamInfo->getName() . " (" . $teamInfo->getRoom() . ")";

        return [
            'id' => $print->getPrintid(),
            'time' => $print->getTime(),
            'lang' => $lang,
            'team' => $team,
            'filename' => $print->getFilename(),
            'processed' => true,
            'done' => false,
            'sourcecode' => base64_encode($print->getSourcecode())
        ];
    }
}
