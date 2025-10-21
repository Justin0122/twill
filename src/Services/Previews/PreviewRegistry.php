<?php
namespace A17\Twill\Services\Previews;

use A17\Twill\Models\Block;
use A17\Twill\Models\AppSetting;
use App\Models\Page;

class PreviewRegistry
{
    protected ?int $pageId = null;
    /** @var array<string,\A17\Twill\Models\Block> */
    protected array $byType = [];
    protected bool $initialized = false;

    public function __construct(?int $pageId = null)
    {
        $this->pageId = $pageId ?? $this->resolvePageIdFromTwillBlocks();
    }

    public function forType(?string $type): ?Block
    {
        if (!$type) return null;
        $this->init();

        if (isset($this->byType[$type])) {
            return $this->byType[$type];
        }

        $norm = $this->normalizeType($type);
        return $norm ? ($this->byType[$norm] ?? null) : null;
    }

    protected function init(): void
    {
        if ($this->initialized || !$this->pageId) { $this->initialized = true; return; }

        /** @var Page|null $page */
        $page = Page::query()
            ->with(['blocks.children', 'blocks.medias'])
            ->find($this->pageId);

        if (!$page) { $this->initialized = true; return; }

        // Only register TOP-LEVEL blocks: exclude anything with a non-null child_key.
        foreach (($page->blocks ?? collect()) as $b) {
            if ($b->child_key !== null) {
                // skip children / dynamic repeater items
                continue;
            }

            $t = (string) ($b->type ?? '');
            if ($t === '') continue;

            // Canonical key: raw DB type (e.g. "app-textcolumns")
            $this->setAliasOnce($t, $b);

            // Twill UI alias seen in drag list: "a17-block-{type}"
            $this->setAliasOnce("a17-block-{$t}", $b);
        }

        $this->initialized = true;
    }

    protected function setAliasOnce(string $key, Block $b): void
    {
        if (!isset($this->byType[$key])) {
            $this->byType[$key] = $b;
        }
    }

    protected function normalizeType(?string $type): ?string
    {
        if (!$type) return null;
        return preg_replace('/^a17-block-/', '', $type) ?: null;
    }

    /**
     * Pull the preview page id from twill_blocks AppSetting row:
     * Example: {"browsers":{"page":[17]}}
     */
    protected function resolvePageIdFromTwillBlocks(): ?int
    {
        $block = Block::query()
            ->where('blockable_type', AppSetting::class)
            ->where(function ($q) {
                $q->where('editor_name', 'previews')
                    ->orWhere('type', 'like', 'appSettings.previews.%');
            })
            ->orderByDesc('id')
            ->first();

        if (!$block) return null;

        $content = is_array($block->content)
            ? $block->content
            : (json_decode((string) $block->content, true) ?: []);

        $ids = data_get($content, 'browsers.page', []);
        $first = is_array($ids) ? (int)($ids[0] ?? 0) : (int)$ids;

        return $first ?: null;
    }
}
