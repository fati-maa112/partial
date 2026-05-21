<?php
// src/Entity/CartItem.php

namespace App\Entity;

use App\Repository\CartItemRepository;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;

#[ORM\Entity(repositoryClass: CartItemRepository::class)]
#[ORM\Table(name: 'cart_item')]
#[ApiResource]
#[ORM\UniqueConstraint(
    name: 'unique_user_product',
    columns: ['user_id', 'product_id']
)]
class CartItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // ─── Relationship: One user → many cart items ──────────────────────────
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    // ─── Relationship: One product → many cart items ───────────────────────
    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\Column(type: 'integer')]
    private int $quantity = 1;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // ─── Getters / Setters ─────────────────────────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;
        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    // ─── Helper: computed subtotal ─────────────────────────────────────────

    public function getSubtotal(): float
    {
        return (float) $this->product?->getPrice() * $this->quantity;
    }

    // ─── Helper: serialize for JSON response ──────────────────────────────

    public function toArray(): array
    {
        $product = $this->product;

        return [
            'id'         => $this->id,
            'quantity'   => $this->quantity,
            'subtotal'   => $this->getSubtotal(),
            'product'    => [
                'id'          => $product?->getId(),
                'name'        => $product?->getName(),
                'price'       => $product?->getPrice(),
                'image'       => $product?->getImage(),
                'stock'       => $product?->getQuantity(),
                'description' => $product?->getDescription(),
                'category'    => $product?->getCategory()?->getName(),
            ],
        ];
    }
}