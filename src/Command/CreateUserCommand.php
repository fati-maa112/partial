<?php
// src/Command/CreateUserCommand.php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Creates a new user account (staff/admin)',
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::OPTIONAL, 'The username')
            ->addArgument('password', InputArgument::OPTIONAL, 'The password')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Make this user an admin')
            ->addOption('staff', null, InputOption::VALUE_NONE, 'Make this user a staff member')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'User email')
            ->addOption('firstname', null, InputOption::VALUE_REQUIRED, 'First name')
            ->addOption('lastname', null, InputOption::VALUE_REQUIRED, 'Last name')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🌿 Naturae User Creation');

        // Get username
        $username = $input->getArgument('username');
        if (!$username) {
            $question = new Question('Username: ');
            $username = $io->askQuestion($question);
        }

        // Check if user already exists
        $existingUser = $this->entityManager->getRepository(User::class)
            ->findOneBy(['username' => $username]);
        
        if ($existingUser) {
            $io->error('A user with this username already exists!');
            return Command::FAILURE;
        }

        // Get email
        $email = $input->getOption('email');
        if (!$email) {
            $question = new Question('Email: ');
            $email = $io->askQuestion($question);
        }

        // Check if email already exists
        $existingEmail = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => $email]);
        
        if ($existingEmail) {
            $io->error('A user with this email already exists!');
            return Command::FAILURE;
        }

        // Get password
        $password = $input->getArgument('password');
        if (!$password) {
            $question = new Question('Password: ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $password = $io->askQuestion($question);
        }

        // Create user
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        
        // Hash password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        // Set roles
        $roles = ['ROLE_USER'];
        if ($input->getOption('admin')) {
            $roles[] = 'ROLE_ADMIN';
        } elseif ($input->getOption('staff')) {
            $roles[] = 'ROLE_STAFF';
        }
        $user->setRoles($roles);

        // Set optional fields
        if ($firstName = $input->getOption('firstname')) {
            $user->setFirstName($firstName);
        }
        
        if ($lastName = $input->getOption('lastname')) {
            $user->setLastName($lastName);
        }

        // Set status to active
        $user->setStatus('active');

        // Save to database
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success('User created successfully!');
        
        // Display user information
        $io->section('User Details');
        $io->table(
            ['Property', 'Value'],
            [
                ['Username', $user->getUsername()],
                ['Email', $user->getEmail()],
                ['Full Name', $user->getFullName()],
                ['Roles', implode(', ', $user->getRoles())],
                ['Primary Role', $user->getPrimaryRole()],
                ['Status', $user->getStatus()],
                ['Created At', $user->getCreatedAt()->format('Y-m-d H:i:s')],
            ]
        );

        $io->note([
            'User can now login at: /login',
            'Username: ' . $user->getUsername(),
            'Email: ' . $user->getEmail(),
        ]);

        return Command::SUCCESS;
    }
}

// ============================================================================

// src/Command/ListUsersCommand.php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:list-users',
    description: 'Lists all users in the system',
)]
class ListUsersCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🌿 Naturae Users List');

        $users = $this->entityManager->getRepository(User::class)->findAll();

        if (empty($users)) {
            $io->warning('No users found in the system.');
            return Command::SUCCESS;
        }

        $tableData = [];
        foreach ($users as $user) {
            $tableData[] = [
                $user->getId(),
                $user->getUsername(),
                $user->getEmail(),
                $user->getFullName(),
                $user->getPrimaryRole(),
                $user->getStatus(),
                $user->getCreatedAt()->format('Y-m-d'),
            ];
        }

        $io->table(
            ['ID', 'Username', 'Email', 'Full Name', 'Role', 'Status', 'Created'],
            $tableData
        );

        $io->success(sprintf('Total users: %d', count($users)));

        return Command::SUCCESS;
    }
}

// ============================================================================

// src/Command/ChangeUserPasswordCommand.php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:change-password',
    description: 'Change a user password (admin function)',
)]
class ChangeUserPasswordCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'The username')
            ->addArgument('new-password', InputArgument::OPTIONAL, 'The new password')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🌿 Change User Password');

        $username = $input->getArgument('username');

        // Find user
        $user = $this->entityManager->getRepository(User::class)
            ->findOneBy(['username' => $username]);

        if (!$user) {
            $io->error(sprintf('User "%s" not found!', $username));
            return Command::FAILURE;
        }

        // Get new password
        $newPassword = $input->getArgument('new-password');
        if (!$newPassword) {
            $question = new Question('New password: ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $newPassword = $io->askQuestion($question);
            
            // Confirm password
            $question = new Question('Confirm password: ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $confirmPassword = $io->askQuestion($question);

            if ($newPassword !== $confirmPassword) {
                $io->error('Passwords do not match!');
                return Command::FAILURE;
            }
        }

        // Hash and set new password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        $user->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        $io->success(sprintf('Password changed successfully for user: %s', $username));
        
        $io->note([
            'User: ' . $user->getUsername(),
            'Email: ' . $user->getEmail(),
            'Full Name: ' . $user->getFullName(),
        ]);

        return Command::SUCCESS;
    }
}

// ============================================================================

// src/Command/PromoteUserCommand.php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:promote-user',
    description: 'Promote a user to staff or admin',
)]
class PromoteUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'The username')
            ->addArgument('role', InputArgument::REQUIRED, 'The role (staff or admin)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $username = $input->getArgument('username');
        $role = strtolower($input->getArgument('role'));

        // Find user
        $user = $this->entityManager->getRepository(User::class)
            ->findOneBy(['username' => $username]);

        if (!$user) {
            $io->error(sprintf('User "%s" not found!', $username));
            return Command::FAILURE;
        }

        // Determine role
        $roleConstant = match($role) {
            'admin' => 'ROLE_ADMIN',
            'staff' => 'ROLE_STAFF',
            default => null,
        };

        if (!$roleConstant) {
            $io->error('Invalid role. Use "staff" or "admin".');
            return Command::FAILURE;
        }

        // Add role
        $currentRoles = $user->getRoles();
        if (in_array($roleConstant, $currentRoles)) {
            $io->warning(sprintf('User already has role: %s', $roleConstant));
            return Command::SUCCESS;
        }

        $currentRoles[] = $roleConstant;
        $user->setRoles(array_unique($currentRoles));
        $user->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        $io->success(sprintf('User "%s" promoted to %s!', $username, strtoupper($role)));
        
        $io->table(
            ['Property', 'Value'],
            [
                ['Username', $user->getUsername()],
                ['Email', $user->getEmail()],
                ['All Roles', implode(', ', $user->getRoles())],
                ['Primary Role', $user->getPrimaryRole()],
            ]
        );

        return Command::SUCCESS;
    }
}