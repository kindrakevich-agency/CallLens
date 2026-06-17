<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\Audit\AuditLog;
use App\Domain\User\Role;
use App\Domain\User\User;
use App\Infrastructure\Doctrine\Repository\AuditLogRepository;
use App\Infrastructure\Doctrine\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Workspace team & roles management (spec §11). Listing is open to any member;
 * inviting, changing roles and removing members require admin+. The list is
 * tenant-scoped by the Doctrine filter. Guardrails: you cannot change your own
 * role, remove yourself, or leave the workspace without an owner; only an owner
 * may grant the owner role.
 */
final class TeamController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly EntityManagerInterface $em,
        private readonly AuditLogRepository $auditLogs,
    ) {
    }

    #[Route('/api/v1/team', name: 'team_list', methods: ['GET'])]
    public function list(#[CurrentUser] User $me): JsonResponse
    {
        $items = array_map(
            fn (User $u) => $this->payload($u, $me),
            $this->users->findBy([], ['createdAt' => 'ASC']),
        );

        return new JsonResponse(['items' => $items]);
    }

    #[Route('/api/v1/team/invite', name: 'team_invite', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function invite(Request $request, #[CurrentUser] User $me): JsonResponse
    {
        $data = $this->decode($request);
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $name = trim((string) ($data['name'] ?? ''));
        $role = Role::tryFrom((string) ($data['role'] ?? 'viewer')) ?? Role::Viewer;

        if (!filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return $this->error('A valid email is required.');
        }
        if ($name === '') {
            return $this->error('A name is required.');
        }
        if ($role === Role::Owner && $me->role() !== Role::Owner) {
            return $this->error('Only an owner can grant the owner role.', Response::HTTP_FORBIDDEN);
        }
        if ($this->users->findByEmail($email) !== null) {
            return $this->error('An account with this email already exists.', Response::HTTP_CONFLICT);
        }

        // Create the member with a random temporary password the inviter shares.
        // The invitee can set their own via the password-reset flow.
        $tempPassword = bin2hex(random_bytes(8));
        $user = new User($me->tenant(), $email, $name, $role);
        $user->setPasswordHash($this->hasher->hashPassword($user, $tempPassword));
        $this->users->save($user, true);

        $this->auditLogs->save(new AuditLog(
            action: 'team.invited',
            tenant: $me->tenant(),
            user: $me,
            target: $email,
            ip: $request->getClientIp(),
        ));

        return new JsonResponse(
            ['member' => $this->payload($user, $me), 'temporary_password' => $tempPassword],
            Response::HTTP_CREATED,
        );
    }

    #[Route('/api/v1/team/{id}/role', name: 'team_role', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function changeRole(string $id, Request $request, #[CurrentUser] User $me): JsonResponse
    {
        $member = $this->find($id);
        if ($member === null) {
            return $this->error('Member not found.', Response::HTTP_NOT_FOUND);
        }
        if ($member->id()->equals($me->id())) {
            return $this->error('You cannot change your own role.');
        }

        $role = Role::tryFrom((string) ($this->decode($request)['role'] ?? ''));
        if ($role === null) {
            return $this->error('A valid role is required.');
        }
        if ($role === Role::Owner && $me->role() !== Role::Owner) {
            return $this->error('Only an owner can grant the owner role.', Response::HTTP_FORBIDDEN);
        }
        if ($member->role() === Role::Owner && $role !== Role::Owner && $this->ownerCount() <= 1) {
            return $this->error('The workspace must keep at least one owner.');
        }

        $member->setRole($role);
        $this->em->flush();

        $this->auditLogs->save(new AuditLog(
            action: 'team.role_changed',
            tenant: $me->tenant(),
            user: $me,
            target: $member->email() . ' → ' . $role->value,
            ip: $request->getClientIp(),
        ));

        return new JsonResponse($this->payload($member, $me));
    }

    #[Route('/api/v1/team/{id}', name: 'team_remove', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function remove(string $id, Request $request, #[CurrentUser] User $me): JsonResponse
    {
        $member = $this->find($id);
        if ($member === null) {
            return $this->error('Member not found.', Response::HTTP_NOT_FOUND);
        }
        if ($member->id()->equals($me->id())) {
            return $this->error('You cannot remove yourself.');
        }
        if ($member->role() === Role::Owner && $this->ownerCount() <= 1) {
            return $this->error('The workspace must keep at least one owner.');
        }

        $email = $member->email();
        $this->em->remove($member);
        $this->em->flush();

        $this->auditLogs->save(new AuditLog(
            action: 'team.removed',
            tenant: $me->tenant(),
            user: $me,
            target: $email,
            ip: $request->getClientIp(),
        ));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function find(string $id): ?User
    {
        return Uuid::isValid($id) ? $this->users->find(Uuid::fromString($id)) : null;
    }

    private function ownerCount(): int
    {
        return (int) $this->users->count(['role' => Role::Owner]);
    }

    private function payload(User $u, UserInterface $me): array
    {
        return [
            'id' => (string) $u->id(),
            'email' => $u->email(),
            'name' => $u->name(),
            'role' => $u->role()->value,
            'email_verified' => $u->isEmailVerified(),
            'is_self' => $u instanceof User && $me instanceof User && $u->id()->equals($me->id()),
        ];
    }

    /** @return array<string,mixed> */
    private function decode(Request $request): array
    {
        $data = json_decode($request->getContent(), true);

        return \is_array($data) ? $data : [];
    }

    private function error(string $message, int $status = Response::HTTP_UNPROCESSABLE_ENTITY): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }
}
