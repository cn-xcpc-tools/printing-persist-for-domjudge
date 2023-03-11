<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Prints;
use App\Entity\PrintWithSourceCode;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use OpenApi\Annotations as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Rest\Route("/printing")
 * @OA\Tag(name="Printings")
 * @OA\Response(response="404", ref="#/components/responses/NotFound")
 * @OA\Response(response="401", ref="#/components/responses/Unauthorized")
 * @OA\Response(response="400", ref="#/components/responses/InvalidResponse")
 * @Security("is_granted('ROLE_JURY') or is_granted('ROLE_BALLOON')")
 */
class PrintingController extends AbstractFOSRestController
{
    protected EntityManagerInterface $em;

    public function __construct(
        EntityManagerInterface $em
    ) {
        $this->em = $em;
    }

    /**
     * Set the printing as resolved.
     * @Rest\Post("/set-done/{printId}")
     * @OA\Response(
     *     response="200",
     *     description="The basic information for this printing"
     * )
     * @return array|string
     */
    public function setDoneAction(int $printId)
    {
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
            : "t" . $teamInfo->getTeamid() . ": " . $teamInfo->getName();

        return [
            'id' => $print->getPrintid(),
            'time' => $print->getTime(),
            'filename' => $print->getFilename(),
            'lang' => $lang,
            'team' => $team,
            'room' => $teamInfo == null ? null : $teamInfo->getRoom(),
            'processed' => true,
            'done' => true
        ];
    }

    /**
     * Get the next printing and mark as processed.
     * @Rest\Post("/next-printing")
     * @OA\Response(
     *     response="200",
     *     description="The next printing"
     * )
     * @return array|string
     */
    public function getNextPrintingAction()
    {
        $query = $this->em->createQueryBuilder()
            ->select('b')
            ->from(Prints::class, 'b')
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
            $numUpdated = $this->em->getConnection()->executeStatement(
                'UPDATE print SET processed = 1 WHERE printid = :printid AND processed = 0',
                [
                    'printid' => $print->getPrintid()
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
            : "t" . $teamInfo->getTeamid() . ": " . $teamInfo->getName();

        return [
            'id' => $print->getPrintid(),
            'time' => $print->getTime(),
            'lang' => $lang,
            'team' => $team,
            'filename' => $print->getFilename(),
            'room' => $teamInfo == null ? null : $teamInfo->getRoom(),
            'processed' => true,
            'done' => false,
            'sourcecode' => base64_encode($print->getSourcecode())
        ];
    }
}
