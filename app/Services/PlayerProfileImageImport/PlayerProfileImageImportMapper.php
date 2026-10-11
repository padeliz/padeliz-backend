<?php

declare(strict_types=1);

namespace App\Services\PlayerProfileImageImport;

use App\Models\User;
use App\Models\UserEmail;
use App\Models\UserPhone;
use App\Support\PlayerDisplayName;
use App\Support\PlayerFullNameMatchIndex;
use App\Support\PlayerPhoneNormalizer;
use Illuminate\Support\Collection;

final class PlayerProfileImageImportMapper
{
    public function __construct(
        private readonly PlayerProfileImageImportFilenameParser $parser,
    ) {
    }

    /**
     * @param  list<array{index: int, filename: string}>  $rows
     * @return list<array{
     *     index: int,
     *     filename: string,
     *     mapping: array{
     *         status: string,
     *         matched_by: ?string,
     *         player: ?User,
     *         candidates: list<User>,
     *         name_matches: list<User>
     *     }
     * }>
     */
    public function mapFilenames(array $rows): array
    {
        $emailOwners = $this->emailOwnerIndex();
        $phoneOwners = $this->phoneOwnerIndex();
        $nameLookups = [];
        foreach ($rows as $row) {
            $parsed = $this->parser->parse($row['filename']);
            if ($parsed['type'] === PlayerProfileImageImportFilenameParser::TYPE_NAME) {
                $nameLookups[] = $parsed['stem'];
            }
        }
        $nameIndex = PlayerFullNameMatchIndex::build($nameLookups);
        $playerIds = [];

        $mapped = [];
        foreach ($rows as $row) {
            $parsed = $this->parser->parse($row['filename']);
            $status = 'unmapped';
            $matchedBy = null;
            $playerId = null;
            $nameMatchIds = [];

            if ($parsed['type'] === PlayerProfileImageImportFilenameParser::TYPE_EMAIL) {
                $emailKey = mb_strtolower($parsed['stem']);
                $owners = $emailOwners[$emailKey] ?? [];
                if (count($owners) === 1) {
                    $status = 'mapped';
                    $matchedBy = 'email';
                    $playerId = $owners[0];
                }
            } elseif ($parsed['type'] === PlayerProfileImageImportFilenameParser::TYPE_PHONE) {
                $owners = $phoneOwners[$parsed['stem']] ?? [];
                if (count($owners) === 1) {
                    $status = 'mapped';
                    $matchedBy = 'phone';
                    $playerId = $owners[0];
                }
            } elseif ($parsed['type'] === PlayerProfileImageImportFilenameParser::TYPE_ID) {
                $id = (int) $parsed['stem'];
                if ($id > 0 && User::query()->whereKey($id)->exists()) {
                    $status = 'mapped';
                    $matchedBy = 'id';
                    $playerId = $id;
                }
            } elseif ($parsed['type'] === PlayerProfileImageImportFilenameParser::TYPE_NAME) {
                $lookup = PlayerDisplayName::normalizeStem($parsed['stem']);
                $nameMatchIds = $lookup === '' ? [] : ($nameIndex[$lookup] ?? []);
            }

            foreach (array_merge($playerId !== null ? [$playerId] : [], $nameMatchIds) as $id) {
                $playerIds[$id] = true;
            }

            $mapped[] = [
                'index' => $row['index'],
                'filename' => $row['filename'],
                'mapping' => [
                    'status' => $status,
                    'matched_by' => $matchedBy,
                    'player_user_id' => $playerId,
                    'name_match_ids' => $nameMatchIds,
                ],
            ];
        }

        $players = empty($playerIds)
            ? collect()
            : User::query()
                ->with('profileImages')
                ->whereIn('id_user', array_keys($playerIds))
                ->get()
                ->keyBy('id_user');

        foreach ($mapped as &$row) {
            $mapping = $row['mapping'];
            $row['mapping'] = [
                'status' => $mapping['status'],
                'matched_by' => $mapping['matched_by'],
                'player' => $mapping['player_user_id'] !== null
                    ? $players->get($mapping['player_user_id'])
                    : null,
                'candidates' => [],
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
