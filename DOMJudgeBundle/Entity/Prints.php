<?php declare(strict_types=1);

namespace DOMJudgeBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * Prints to be handed out
 * @ORM\Entity()
 * @ORM\Table(name="print", options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"})
 */
class Prints
{

    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="printid", options={"comment"="Unique ID"}, nullable=false)
     */
    private $printid;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="time", options={"comment"="Timestamp of the print request",
     *                             "unsigned"=true}, nullable=false)
     * @Serializer\Exclude()
     */
    private $time;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="userid", options={"comment"="User ID associated to this entry"}, nullable=false)
     * @Serializer\SerializedName("user_id")
     * @Serializer\Type("string")
     */
    private $userid;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="done", options={"comment"="Has been handed out yet?"}, nullable=false)
     */
    private $done = false;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="processed", options={"comment"="Has been printed?"}, nullable=false)
     */
    private $processed = false;

    /**
     * @var string
     * @ORM\Column(type="string", name="filename", length=255, options={"comment"="Filename as submitted"}, nullable=false)
     */
    private $filename;

    /**
     * @var int
     *
     * @ORM\Column(type="string", name="langid", options={"comment"="Language ID"}, nullable=true)
     * @Serializer\Exclude()
     */
    private $langid;

    /**
     * @ORM\ManyToOne(targetEntity="User", inversedBy="prints")
     * @ORM\JoinColumn(name="userid", referencedColumnName="userid", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private $user;


    /**
     * Get printid
     *
     * @return integer
     */
    public function getPrintid()
    {
        return $this->printid;
    }

    /**
     * Set time
     *
     * @param string $time
     *
     * @return Prints
     */
    public function setTime($time)
    {
        $this->time = $time;

        return $this;
    }

    /**
     * Get time
     *
     * @return string
     */
    public function getTime()
    {
        return $this->time;
    }

    /**
     * Set userid
     *
     * @param integer $userid
     *
     * @return Prints
     */
    public function setUserid($userid)
    {
        $this->userid = $userid;

        return $this;
    }

    /**
     * Get userid
     *
     * @return integer
     */
    public function getUserid()
    {
        return $this->userid;
    }

    /**
     * Set user
     *
     * @param User $user
     *
     * @return Prints
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set done
     *
     * @param boolean $done
     *
     * @return Prints
     */
    public function setDone($done)
    {
        $this->done = $done;

        return $this;
    }

    /**
     * Get done
     *
     * @return boolean
     */
    public function getDone()
    {
        return $this->done;
    }

    /**
     * Set processed
     *
     * @param boolean $processed
     *
     * @return Prints
     */
    public function setProcessed($processed)
    {
        $this->processed = $processed;

        return $this;
    }

    /**
     * Get processed
     *
     * @return boolean
     */
    public function getProcessed()
    {
        return $this->processed;
    }

    /**
     * Set filename
     *
     * @param string $filename
     *
     * @return Prints
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;

        return $this;
    }

    /**
     * Get filename
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * Set langid
     *
     * @param string $langid
     *
     * @return Prints
     */
    public function setLangid($langid)
    {
        $this->langid = $langid;

        return $this;
    }

    /**
     * Get langid
     *
     * @return string
     */
    public function getLangid()
    {
        return $this->langid;
    }
}
