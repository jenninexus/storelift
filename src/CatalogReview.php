<?php

declare(strict_types=1);

namespace StoreLift;

final class CatalogReview
{
    public static function apply(Config $config, array $data, ?array $draft, string $owner): array
    {
        $action = $data['action'] ?? null;
        if (in_array($action, ['import', 'import_csv'], true)) {
            $source = $action === 'import' ? ($data['url'] ?? null) : 'csv';
            if ($action === 'import' && $source !== CatalogImport::SOURCE_URL) {
                throw new HttpError(400, 'unsupported_source', 'Use the JenniNexus example URL or upload a StoreLift CSV.');
            }
            $next = [
                'id' => bin2hex(random_bytes(16)),
                'source' => $source,
                'items' => $action === 'import' ? CatalogImport::fetch($source) : CatalogCsv::parse($data['csv'] ?? null),
                'ai' => null,
            ];
        } else {
            if (!in_array($action, ['suggest', 'approve'], true)) {
                throw new HttpError(400, 'invalid_action', 'Choose an available catalog action.');
            }
            if ($draft === null || $draft['id'] !== ($data['draftId'] ?? null)) {
                self::stale();
            }
            $next = $draft;
            if ($action === 'suggest') {
                $next['ai'] = CatalogAi::suggest($draft['items'], $config->aiEndpoint, $config->aiModel, $config->aiKey);
                $next['id'] = bin2hex(random_bytes(16));
            } else {
                $next = self::approve($draft, $data['edits'] ?? null);
            }
        }

        if (!session_start(['use_strict_mode' => 1, 'use_only_cookies' => 1, 'gc_maxlifetime' => 86400])) {
            throw new HttpError(503, 'session_unavailable', 'StoreLift could not save the catalog.');
        }
        try {
            if (($_SESSION['owner'] ?? null) !== $owner
                || ($_SESSION['catalogDraft'] ?? null) !== $draft) {
                self::stale();
            }
            if ($action === 'approve') {
                $_SESSION['catalogApproved'] = $next;
                $_SESSION['catalogDraft']['id'] = bin2hex(random_bytes(16));
            } else {
                $_SESSION['catalogDraft'] = $next;
            }
        } finally {
            session_write_close();
        }
        return ['saved' => true];
    }

    private static function approve(array $draft, mixed $edits): array
    {
        if (!is_array($edits) || count($edits) !== count($draft['items'])) {
            throw new HttpError(400, 'invalid_edits', 'Review every listing before approving the catalog.');
        }
        $items = [];
        foreach ($draft['items'] as $item) {
            $description = $edits[$item['sku']] ?? null;
            if (!is_string($description) || trim($description) === '' || strlen($description) > 1800) {
                throw new HttpError(400, 'invalid_description', 'Each listing needs a short description.');
            }
            $item['description'] = trim($description);
            $items[] = $item;
        }
        return [
            'revision' => bin2hex(random_bytes(8)),
            'approvedAt' => gmdate(DATE_ATOM),
            'source' => $draft['source'],
            'ai' => $draft['ai'] === null ? null : array_intersect_key($draft['ai'], array_flip(['model', 'requestId'])),
            'items' => $items,
        ];
    }

    private static function stale(): never
    {
        throw new HttpError(409, 'stale_catalog', 'This draft changed. Refresh the page to review the latest version.');
    }
}
