<?php
declare(strict_types = 1);

namespace SignNow\Api\Entity\Invite;

use JMS\Serializer\Annotation as Serializer;

/**
 * Class Recipient
 *
 * @package SignNow\Api\Entity\Invite
 */
class Recipient
{
    /**
     * @var string
     * @Serializer\Type("string")
     */
    protected $email;

    /**
     * @var string
     * @Serializer\Type("string")
     */
    protected $role;

    /**
     * @var int|null
     * @Serializer\Type("int")
     */
    protected $order;

    /**
     * @var string
     * @Serializer\Type("string")
     */
    protected $roleId;

    /**
     * @var int|null
     * @Serializer\Type("int")
     */
    protected $reminder;

    /**
     * @var int|null
     * @Serializer\Type("int")
     */
    protected $expirationDays;

    /**
     * @var string
     * @Serializer\Type("string")
     */
    protected $subject;

    /**
     * Recipient constructor.
     *
     * @param string   $email
     * @param string   $role
     * @param string   $roleId
     * @param int|null $order
     * @param int|null $reminder
     * @param int|null $expirationDays
     * @param string   $subject
     */
    public function __construct(string $email, string $role, string $roleId, ?int $order = null, ?int $reminder = null, ?int $expirationDays=null, string $subject)
    {
        $this->email = $email;
        $this->role = $role;
        $this->roleId = $roleId;
        $this->order = $order > 0 ? $order : 1;
        $this->reminder = $reminder > 0 ? $reminder : 0;
        $this->expirationDays = $expirationDays > 0 ? $expirationDays : 30;
        $this->subject = $subject;
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @return string
     */
    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * @return int
     */
    public function getOrder(): int
    {
        return $this->order;
    }

    /**
     * @return string
     */
    public function getRoleId(): string
    {
        return $this->roleId;
    }

    /**
     * @return int
     */
    public function getReminder(): int
    {
        return $this->reminder;
    }

    /**
     * @return int
     */
    public function getExpirationDays(): int
    {
        return $this->expirationDays;
    }


    /**
     * @return string
     */
    public function getSubject(): string
    {
        return $this->subject;
    }
}
