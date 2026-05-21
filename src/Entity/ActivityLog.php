<?php
// src/Entity/ActivityLog.php

namespace App\Entity;

use App\Repository\ActivityLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;

#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
#[ORM\Table(name: 'activity_logs')]
#[ORM\Index(columns: ['username'], name: 'idx_username')]
#[ORM\Index(columns: ['action'], name: 'idx_action')]
#[ORM\Index(columns: ['created_at'], name: 'idx_created_at')]
#[ApiResource]
class ActivityLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $username = null;

    #[ORM\Column(length: 50)]
    private ?string $role = null;

    #[ORM\Column(length: 50)]
    private ?string $action = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $targetData = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userAgent = null;

   public function __construct()
{
    // Set timezone to Philippines (UTC+8)
    $timezone = new \DateTimeZone('Asia/Manila');
    $this->createdAt = new \DateTime('now', $timezone);
}

    // Getters and Setters
    public function getId(): ?int 
    { 
        return $this->id; 
    }
    
    public function getUsername(): ?string 
    { 
        return $this->username; 
    }
    
    public function setUsername(string $username): static 
    { 
        $this->username = $username; 
        return $this; 
    }
    
    public function getRole(): ?string 
    { 
        return $this->role; 
    }
    
    public function setRole(string $role): static 
    { 
        $this->role = $role; 
        return $this; 
    }
    
    public function getAction(): ?string 
    { 
        return $this->action; 
    }
    
    public function setAction(string $action): static 
    { 
        $this->action = $action; 
        return $this; 
    }
    
    public function getTargetData(): ?string 
    { 
        return $this->targetData; 
    }
    
    public function setTargetData(string $targetData): static 
    { 
        $this->targetData = $targetData; 
        return $this; 
    }
    
    public function getCreatedAt(): ?\DateTimeInterface 
    { 
        return $this->createdAt; 
    }
    
    public function setCreatedAt(\DateTimeInterface $createdAt): static 
    { 
        $this->createdAt = $createdAt; 
        return $this; 
    }
    
    public function getIpAddress(): ?string 
    { 
        return $this->ipAddress; 
    }
    
    public function setIpAddress(?string $ipAddress): static 
    { 
        $this->ipAddress = $ipAddress; 
        return $this; 
    }
    
    public function getUserAgent(): ?string 
    { 
        return $this->userAgent; 
    }
    
    public function setUserAgent(?string $userAgent): static 
    { 
        $this->userAgent = $userAgent; 
        return $this; 
    }
}