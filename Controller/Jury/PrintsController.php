<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\EventLogService;
use DOMJudgeBundle\Utils\Utils;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Route("/jury/prints")
 * @Security("has_role('ROLE_JURY') or has_role('ROLE_BALLOON')")
 */
class PrintsController extends Controller
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
     * PrintsController constructor.
     * @param EntityManagerInterface $em
     * @param DOMJudgeService        $dj
     * @param EventLogService        $eventLogService
     */
    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        EventLogService $eventLogService
    ) {
        $this->em              = $em;
        $this->dj              = $dj;
        $this->eventLogService = $eventLogService;
    }

    /**
     * @Route("", name="jury_prints")
     */
    public function indexAction(Request $request, Packages $assetPackage, KernelInterface $kernel)
    {
        $timeFormat = (string)$this->dj->dbconfig_get('time_format', '%H:%M');

        $em = $this->em;

        $query = $em->createQueryBuilder()
            ->select('b')
            ->from('DOMJudgeBundle:Prints', 'b');

        $prints = $query->getQuery()->getResult();
        var_dump($prints);
    }

    /**
     * @Route("/{printId}/done", name="jury_prints_setdone")
     */
    public function setDoneAction(Request $request, int $printId)
    {
        $em = $this->em;
        $print = $em->getRepository(Balloon::class)->find($printId);
        if (!$print) {
            throw new NotFoundHttpException('balloon not found');
        }
        $print->setDone(true);
        $em->flush();

        return $this->redirectToRoute("jury_prints");
    }
}
