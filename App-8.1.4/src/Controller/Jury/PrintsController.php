<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Entity\PrintWithSourceCode;
use App\Service\ConfigurationService;
use App\Utils\Utils;
use App\Entity\Prints;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @Route("/jury/prints")
 * @Security("is_granted('ROLE_JURY') or is_granted('ROLE_BALLOON')")
 */
class PrintsController extends BaseController
{
    protected EntityManagerInterface $em;
    protected ConfigurationService $config;

    public function __construct(
        EntityManagerInterface $em,
        ConfigurationService $config
    ) {
        $this->em     = $em;
        $this->config = $config;
    }

    /**
     * @Route("", name="jury_prints")
     */
    public function indexAction(Request $request, Packages $assetPackage, KernelInterface $kernel)
    {
        $timeFormat = (string)$this->config->get('time_format');

        $em = $this->em;

        $query = $em->createQueryBuilder()
            ->select('b.printid', 'b.time', 'b.done', 'b.processed', 'b.filename', 'b.langid', 'b.userid',
                't.teamid', 't.name AS teamname', 't.room', 'u.name AS username')
            ->from(Prints::class, 'b')
            ->leftJoin('b.user', 'u')
            ->leftJoin('u.team', 't')
            ->orderBy('b.time', 'DESC');
        $prints = $query->getQuery()->getResult();

        $table_fields = [
            'status' => ['title' => '', 'sort' => true],
            'printid' => ['title' => 'ID', 'sort' => true],
            'time' => ['title' => 'time', 'sort' => true],
            'team' => ['title' => 'team', 'sort' => true],
            'location' => ['title' => 'loc.', 'sort' => true],
            'filename' => ['title' => 'file', 'sort' => false],
            'langid' => ['title' => 'lang', 'sort' => false],
        ];

        $prints_table = [];

        foreach ($prints as $print) {
            $printdata = [];
            $printactions = [[
                'icon' => 'file-download',
                'title' => 'download print file',
                'link' => $this->generateUrl('jury_prints_download', [
                    'printId' => $print['printid'],
                ])]];

            $printdata['printid']['value'] = $print['printid'];
            $printdata['time']['value'] = Utils::printtime($print['time'], $timeFormat);
            $printdata['team']['value'] = $print['teamid'] === null
                ? "u" . $print['userid'] . ": " . $print['username']
                : "t" . $print['teamid'] . ": " . $print['teamname'];
            $printdata['location']['value'] = $print['teamid'] === null ? "-" : $print['room'];
            $printdata['filename']['value'] = $print['filename'];
            $printdata['langid']['value'] = $print['langid'] === "" ? "plain" : $print['langid'];

            if ($print['processed']) {
                $printactions[] = [
                    'icon' => 'copy',
                    'title' => 'redo this print',
                    'link' => $this->generateUrl('jury_prints_setundone', [
                        'printId' => $print['printid'],
                    ])
                ];
            } else {
                $printactions[] = [];
            }

            if ($print['done']) {
                $printdata['status']['value'] = 'ok';
                $printdata['status']['sortvalue'] = '1';
                $printactions[] = [];
            } else {
                $printactions[] = [
                    'icon' => 'running',
                    'title' => 'mark print as done',
                    'link' => $this->generateUrl('jury_prints_setdone', [
                        'printId' => $print['printid'],
                    ])];

                if ($print['processed']) {
                    $printdata['status']['value'] = 'noconn';
                    $printdata['status']['sortvalue'] = '-1';
                } else {
                    $printdata['status']['value'] = 'crit';
                    $printdata['status']['sortvalue'] = '0';
                }
            }

            $prints_table[] = [
                'data' => $printdata,
                'actions' => $printactions,
                'cssclass' => $print['done'] ? 'disabled' : null,
            ];
        }

        return $this->render('jury/prints.html.twig', [
            'refresh' => ['after' => 60, 'url' => $this->generateUrl('jury_prints')],
            'prints' => $prints_table,
            'table_fields' => $table_fields,
            'num_actions' => 3,
        ]);
    }

    /**
     * @Route("/{printId}/done", name="jury_prints_setdone")
     */
    public function setDoneAction(Request $request, int $printId)
    {
        $em = $this->em;
        $print = $em->getRepository(Prints::class)->find($printId);
        if (!$print) {
            throw new NotFoundHttpException('printing not found');
        }
        $print->setDone(true);
        $print->setProcessed(true);
        $em->flush();

        return $this->redirectToRoute("jury_prints");
    }

    /**
     * @Route("/clean", name="jury_prints_clean")
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function cleanAction(Request $request)
    {
        $this->em->getConnection()->executeUpdate(
            'DELETE FROM print WHERE done = 1'
        );

        return $this->redirectToRoute("jury_prints");
    }

    /**
     * @Route("/{printId}/undone", name="jury_prints_setundone")
     */
    public function setUndoneAction(Request $request, int $printId)
    {
        $em = $this->em;
        $print = $em->getRepository(Prints::class)->find($printId);
        if (!$print) {
            throw new NotFoundHttpException('printing not found');
        }
        $print->setDone(false);
        $print->setProcessed(false);
        $em->flush();

        return $this->redirectToRoute("jury_prints");
    }

    /**
     * @Route("/{printId}/download", name="jury_prints_download")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadAction(Request $request, int $printId)
    {
        /** @var PrintWithSourceCode $print */
        $print = $this->em->getRepository(PrintWithSourceCode::class)->find($printId);
        if (!$print) {
            throw new NotFoundHttpException(sprintf('Printing with ID %d not found', $printId));
        }

        $zipFile     = $print->getSourcecode();
        $zipFileSize = strlen($zipFile);
        $filename    = $print->getFilename();

        $response = new StreamedResponse();
        $response->setCallback(function () use ($zipFile) {
            echo $zipFile;
        });
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Content-Length', $zipFileSize);
        $response->headers->set('Content-Transfer-Encoding', 'binary');
        $response->headers->set('Connection', 'Keep-Alive');
        $response->headers->set('Accept-Ranges', 'bytes');

        return $response;
    }
}
