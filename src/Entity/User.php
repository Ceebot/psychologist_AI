<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['login'], message: 'Этот логин уже используется')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    /** Идентификатор пользователя */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'Идентификатор пользователя'])]
    private ?int $id = null;

    /** Логин пользователя (уникальный) */
    #[ORM\Column(length: 180, unique: true, options: ['comment' => 'Логин пользователя'])]
    #[Assert\NotBlank(message: 'Логин не может быть пустым')]
    #[Assert\Length(min: 3, max: 180, minMessage: 'Логин должен содержать минимум {{ limit }} символа', maxMessage: 'Логин не может быть длиннее {{ limit }} символов')]
    private ?string $login = null;

    /** Хэш пароля */
    #[ORM\Column(options: ['comment' => 'Хэш пароля'])]
    private ?string $password = null;

    /** Дата создания пользователя */
    #[ORM\Column(options: ['comment' => 'Дата создания пользователя'])]
    private ?\DateTimeImmutable $createdAt = null;

    /** Коллекция чатов пользователя */
    #[ORM\OneToMany(targetEntity: Chat::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $chats;

    public function __construct()
    {
        $this->chats = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLogin(): ?string
    {
        return $this->login;
    }

    public function setLogin(string $login): static
    {
        $this->login = $login;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getChats(): Collection
    {
        return $this->chats;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->login;
    }
}

