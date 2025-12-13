<?php

namespace App\Form;

use App\Entity\Order;
use App\Entity\Customer;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use App\Form\OrderItemType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // ✅ FIXED: Changed from customer_name to customer relationship
            ->add('customer', EntityType::class, [
                'class' => Customer::class,
                'choice_label' => 'fullName',
                'placeholder' => 'Select a customer',
                'label' => 'Customer Name',
                'attr' => ['class' => 'form-control'],
                'required' => true
            ])
            ->add('orderItems', CollectionType::class, [
                'entry_type' => OrderItemType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'label' => false,
                'required' => false,
            ])
            ->add('total', NumberType::class, [
                'label' => 'Total Amount',
                'attr' => [
                    'class' => 'form-control',
                    'step' => '0.01',
                    'min' => '0',
                    'placeholder' => '0.00'
                ],
                'scale' => 2,
                // Field is shown for information only; not required when submitting via form
                'required' => false,
                'disabled' => true,
                'empty_data' => '0.00',
            ])
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'Pending' => Order::STATUS_PENDING,
                    'Confirmed' => Order::STATUS_CONFIRMED,
                    'Processing' => Order::STATUS_PREPARING,
                    'Completed' => Order::STATUS_COMPLETED,
                    'Cancelled' => Order::STATUS_CANCELLED,
                ],
                'label' => 'Status',
                'attr' => ['class' => 'form-select'],
                'required' => true
            ]);
            // Note: orderItems are embedded via OrderItemType in parent
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Order::class,
        ]);
    }
}