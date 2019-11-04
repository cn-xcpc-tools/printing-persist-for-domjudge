<?php declare(strict_types=1);

namespace DOMJudgeBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use DOMJudgeBundle\Entity\User;
use DOMJudgeBundle\Entity\Language;
use DOMJudgeBundle\Entity\Prints;
use DOMJudgeBundle\Entity\PrintWithSourceCode;
use DOMJudgeBundle\Utils\Utils;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Class PrintingService
 * @package DOMJudgeBundle\Service
 */
class PrintingService
{
    const FILENAME_REGEX = '/^[a-zA-Z0-9][a-zA-Z0-9+_\.-]*$/';

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var DOMJudgeService
     */
    protected $dj;

    public function __construct(
        EntityManagerInterface $em,
        LoggerInterface $logger,
        DOMJudgeService $dj
    ) {
        $this->em                = $em;
        $this->logger            = $logger;
        $this->dj                = $dj;
    }

    /**
     * This function takes a (set of) temporary file(s) of a submission,
     * validates it and puts it into the database. Additionally it
     * moves it to a backup storage.
     * @param User|int            $user
     * @param Language|string     $language
     * @param UploadedFile        $file
     * @param float|null          $submitTime
     * @param string|null         $message
     * @return Prints|null
     * @throws \Doctrine\DBAL\DBALException
     */
    public function submitPrint(
        $user,
        $language,
        $file,
        float $submitTime = null,
        string &$message = null
    ) {
        if (!$user instanceof User) {
            $user = $this->em->getRepository(User::class)->find($user);
        }

        if (empty($user)) {
            throw new BadRequestHttpException("User not found");
        }

        if (empty($submitTime)) {
            $submitTime = Utils::now();
        }

        if (!$file->isValid()) {
            $message = $file->getErrorMessage();
            return null;
        }
        $filename = $file->getClientOriginalName();

        $sourceSize = $this->dj->dbconfig_get('sourcesize_limit');

        if (!$file->isReadable()) {
            $message = sprintf("File '%s' not found (or not readable).", $file->getRealPath());
            return null;
        }
        if (!preg_match(self::FILENAME_REGEX, $file->getClientOriginalName())) {
            $message = sprintf("Illegal filename '%s'.", $file->getClientOriginalName());
            return null;
        }
        $totalSize = $file->getSize();

        if ($totalSize > $sourceSize * 1024) {
            $message = sprintf("Print file is larger than %d kB.", $sourceSize);
            return null;
        }

        $this->logger->info('input verified');

        $print = new PrintWithSourceCode();
        $print
            ->setUser($user)
            ->setTime($submitTime)
            ->setFilename($file->getClientOriginalName())
            ->setLangid($language)
            ->setSourcecode(file_get_contents($file->getRealPath()));

        $this->em->persist($print);

        $this->em->transactional(function () {
            $this->em->flush();
        });

        $message = sprintf("Printing %d saved, please wait...", $print->getPrintid());
        return $print;
    }
}
