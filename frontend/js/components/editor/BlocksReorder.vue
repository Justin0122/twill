<template>
  <div class="reorder">
    <draggable
      class="reorder__list"
      :value="items"
      :handle="'.reorder__handle'"
      @end="onEnd"
    >
      <transition-group name="fade" tag="ul">
        <li v-for="(b, i) in items" :key="b.id" class="reorder__item">
          <span class="reorder__handle" aria-hidden="true">⋮⋮</span>
          <div class="reorder__meta">
            <div class="reorder__title">{{ i + 1 }}. {{ titleOf(b) }}</div>
            <div class="reorder__sub">#{{ b.id }} · {{ b.component }}</div>
          </div>
        </li>
      </transition-group>
    </draggable>
  </div>
</template>

<script>
  import draggable from 'vuedraggable'

  export default {
    name: 'A17BlocksReorder',
    components: { draggable },
    props: {
      items: { type: Array, required: true }
    },
    methods: {
      titleOf(b) {
        // Best-effort label shown in the list
        return b.title || b.label || b.component || this.$t?.('fields.block-editor.block', 'Block')
      },
      onEnd(evt) {
        const { oldIndex, newIndex } = evt
        if (oldIndex === newIndex || oldIndex == null || newIndex == null) return
        this.$emit('reorder', { oldIndex, newIndex })
      }
    }
  }
</script>

<style scoped lang="scss">
  .reorder__list { list-style: none; margin: 0; padding: 0; }
  .reorder__item {
    display: flex; align-items: center;
    padding: 10px 12px; border-bottom: 1px solid $color__border;
    background: $color__block-bg;
  }
  .reorder__handle {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; cursor: grab; user-select: none; font-weight: 700;
    margin-right: 8px;
  }
  .reorder__meta { min-width: 0; }
  .reorder__title { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .reorder__sub { opacity: 0.65; font-size: 12px; }
  .fade-enter-active, .fade-leave-active { transition: opacity .15s; }
  .fade-enter, .fade-leave-to { opacity: 0; }
</style>
