<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Entity\Language;
use App\Form\Type\PrintType;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\PrintingService;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class PrintController
 *
 * @Route("/jury/print")
 * @Security("is_granted('ROLE_JURY') or is_granted('ROLE_BALLOON')")
 *
 * @package App\Controller\Jury
 */
class PrintController extends BaseController
{
    protected EntityManagerInterface $em;
    protected DOMJudgeService $dj;
    protected ConfigurationService $config;
    protected PrintingService $ps;

    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        ConfigurationService $config,
        PrintingService $ps
    ) {
        $this->em     = $em;
        $this->dj     = $dj;
        $this->config = $config;
        $this->ps     = $ps;
    }

    /**
     * @Route("", name="jury_print")
     */
    public function showAction(Request $request): Response
    {
        if (!$this->config->get('print_command')) {
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

            return $this->render('jury/print_result.html.twig', [
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

        return $this->render('jury/print.html.twig', [
            'form' => $form->createView(),
            'languages' => $languages,
        ]);
    }
}
