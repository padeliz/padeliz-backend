<?php

declare(strict_types=1);

namespace App\Services\UserImport;

use App\Models\User;
use App\Models\UserEmail;
use App\Models\UserPhone;
use App\Support\PlayerDisplayName;
use App\Support\PlayerFullNameMatchIndex;
use App\Support\PlayerPhoneNormalizer;
use Illuminate\Support\Collection;

final class UserImportMapper
{
    /**
     * @param  list<array{index: int, first_name: string, middle_name: ?string, last_name: ?string, email: string, phone: string}>  $rows
     * @return list<array{
     *     index: int,
     *     first_name: string,
     *     middle_name: ?string,
     *     last_name: ?string,
     *     email: string,
     *     phone: string,
     *     mapping: array{
     *         status: string,
     *         matched_by: ?string,
     *         player: ?User,
     *         candidates: list<User>,
     *         name_matches: list<User>
     *     }
     * }>
     */
    public function mapRows(array $rows): array
    {
        $emailOwners = $this->emailOwnerIndex();
        $phoneOwners = $this->phoneOwnerIndex();
        $nameLookups = [];
        foreach ($rows as $row) {
            $lookup = PlayerDisplayName::fromParts(
                $row['first_name'],
                $row['middle_name'],
                $row['last_name']
            );
            if ($lookup !== '') {
                $nameLookups[] = $lookup;
            }
        }
        $nameIndex = PlayerFullNameMatchIndex::build($nameLookups);
        $playerIds = [];

        $mapped = [];
        foreach ($rows as $row) {
            $email = $row['email'];
            $phone = $row['phone'];
            $emailPlayerIds = $email !== '' ? ($emailOwners[mb_strtolower($email)] ?? []) : [];
            $phonePlayerIds = $phone !== '' ? ($phoneOwners[$phone] ?? []) : [];

            $emailOwner = count($emailPlayerIds) === 1 ? $emailPlayerIds[0] : null;
            $phoneOwner = count($phonePlayerIds) === 1 ? $phonePlayerIds[0] : null;

            $status = 'unmapped';
            $matchedBy = null;
            $playerId = null;
            $candidateIds = [];

            if ($emailOwner !== null && $phoneOwner !== null && $emailOwner !== $phoneOwner) {
                $status = 'conflict';
                $candidateIds = [$emailOwner, $phoneOwner];
            } elseif ($emailOwner !== null) {
                $status = 'mapped';
                $matchedBy = 'email';
                $playerId = $emailOwner;
            } elseif ($phoneOwner !== null) {
                $status = 'mapped';
                $matchedBy = 'phone';
                $playerId = $phoneOwner;
            }

            $lookup = PlayerDisplayName::fromParts(
                $row['first_name'],
                $row['middle_name'],
                $row['last_name']
            );
            $nameMatchIds = $lookup === '' ? [] : ($nameIndex[$lookup] ?? []);
            if ($playerId !== null) {
                $nameMatchIds = array_values(array_filter(
                    $nameMatchIds,
                    static fn (int $id): bool => $id !== $playerId
                ));
            }

            foreach (array_merge(
                $playerId !== null ? [$playerId] : [],
                $candidateIds,
                $nameMatchIds
            ) as $id) {
                $playerIds[$id] = true;
            }

            $mapped[] = [
                'index' => $row['index'],
                'first_name' => $row['first_name'],
                'middle_name' => $row['middle_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'mapping' => [
                    'status' => $status,
                    'matched_by' => $matchedBy,
                    'user_id' => $playerId,
                    'candidate_ids' => $candidateIds,
                    'name_match_ids' => $nameMatchIds,
                ],
            ];
        }

        $players = empty($playerIds)
            ? collect()
            : User::query()->whereIn('id_user', array_keys($playerIds))->get()->keyBy('id_user');

        foreach ($mapped as &$row) {
            $mapping = $row['mapping'];
            $row['mapping'] = [
                'status' => $mapping['status'],
                'matched_by' => $mapping['matched_by'],
                'player' => $mapping['user_id'] !== null
                    ? $players->get($mapping['user_id'])
                    : null,
                'candidates' => $this->playersInOrder($players, $mapping['candidate_ids']),
                'name_matches' => $this->playersInOrder($players, $mapping['name_match_ids']),
            ];
        }
        unset($row);

        return $mapped;
    }

    /**
     * @return array<string, list<int>>
     */
    private function emailOwnerIndex(): array
    {
        $index = [];
        UserEmail::query()->select(['user_id', 'email'])->each(function (UserEmail $email) use (&$index): void {
            $key = mb_strtolower((string) $email->email);
            $index[$key] ??= [];
            if (! in_array($email->user_id, $index[$key], true)) {
                $index[$key][] = (int) $email->user_id;
            }
        });

        return $index;
    }

    /**
     * @return array<string, list<int>>
     */
    private function phoneOwnerIndex(): array
    {
        $index = [];
        UserPhone::query()->select(['user_id', 'phone'])->each(function (UserPhone $phone) use (&$index): void {
            $normalized = PlayerPhoneNormalizer::normalize((string) $phone->phone);
            if ($normalized === '') {
                return;
            }
            $index[$normalized] ??= [];
            if (! in_array($phone->user_id, $index[$normalized], true)) {
                $index[$normalized][] = (int) $phone->user_id;
            }
        });

        return $index;
    }

    /**
     * @param  Collection<int, User>  $players
     * @param  list<int>  $ids
     * @return list<User>
     */
    private function playersInOrder(Collection $players, array $ids): array
    {
        $ordered = [];
        foreach ($ids as $id) {
            $player = $players->get($id);
            if ($player instanceof User) {
                $ordered[] = $player;
            }
        }

        return $ordered;
    }
}
