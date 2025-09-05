<?php

namespace A17\Twill\Repositories\Behaviors;

use A17\Twill\Jobs\ReorderNestedModuleItems;
use A17\Twill\Models\Contracts\TwillModelContract;

trait HandleNesting
{
    /**
     * Queue name (when using queue/after_response modes).
     */
    protected string $reorderNestedModuleItemsJobQueue = 'nesting';

    /**
     * Queue connection; null = use default (config('queue.default')).
     * e.g. 'redis', 'database', 'sync'
     */
    protected ?string $reorderNestedModuleItemsJobConnection = null;

    /**
     * Dispatch mode:
     *  - 'auto'            -> smart pick (sync for small batches, else after_response/queue)
     *  - 'sync'            -> run immediately in the request (fastest, blocks request)
     *  - 'after_response'  -> run after the HTTP response is sent (no worker if connection is 'sync')
     *  - 'queue'           -> push to the queue (requires a running worker)
     */
    protected string $reorderNestedDispatchMode = 'auto';

    /**
     * If mode is 'auto', run sync when the number of IDs is <= this threshold.
     */
    protected int $reorderNestedSyncThreshold = 50;

    public function forNestedSlug(
        string $nestedSlug,
        array $with = [],
        array $withCount = [],
        array $scopes = []
    ): ?TwillModelContract {
        $targetSlug = collect(explode('/', $nestedSlug))->last();

        $targetItem = $this->forSlug($targetSlug, $with, $withCount, $scopes);

        if (! $targetItem || $nestedSlug !== $targetItem->nestedSlug) {
            return null;
        }

        return $targetItem;
    }

    public function setNewOrder(array $ids): void
    {
        $mode = $this->resolveDispatchMode(count($ids));
        $queue = $this->reorderNestedModuleItemsJobQueue;
        $connection = $this->reorderNestedModuleItemsJobConnection;

        switch ($mode) {
            case 'sync':
                // Executes inline; fastest + no worker required
                ReorderNestedModuleItems::dispatchSync($this->model, $ids);
                break;

            case 'after_response':
                // Runs after the HTTP response is flushed.
                // If your default connection is 'sync', this still avoids a worker.
                $pending = ReorderNestedModuleItems::dispatchAfterResponse($this->model, $ids)
                    ->onQueue($queue);
                if ($connection) {
                    $pending->onConnection($connection);
                }
                break;

            case 'queue':
            default:
                // Standard queued job (requires a running worker)
                $pending = ReorderNestedModuleItems::dispatch($this->model, $ids)
                    ->onQueue($queue);
                if ($connection) {
                    $pending->onConnection($connection);
                }
                break;
        }
    }

    public function afterRestore(TwillModelContract $object): void
    {
        if (! $object->parent) {
            $object->parent_id = null;
            $object->save();
        }
    }

    /**
     * Decide how to dispatch based on configuration and workload size.
     */
    protected function resolveDispatchMode(int $count): string
    {
        $mode = $this->reorderNestedDispatchMode;

        if ($mode !== 'auto') {
            return $mode;
        }

        // If global queue is 'sync', there's no benefit to pushing; just run sync.
        $defaultConn = config('queue.default');
        if ($defaultConn === 'sync') {
            return 'sync';
        }

        // Small reorders are instant inline; large ones run async.
        if ($count <= $this->reorderNestedSyncThreshold) {
            return 'sync';
        }

        return function_exists('fastcgi_finish_request') ? 'after_response' : 'queue';
    }
}
