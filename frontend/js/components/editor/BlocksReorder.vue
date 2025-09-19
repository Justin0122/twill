<template>
  <div class="reorder">
    <draggable
      class="reorder__list"
      :value="items"
      :handle="'.reorder__handle'"
      @start="onStart"
      @end="onEnd"
    >
      <!-- Sliding marker -->
      <div
        class="reorder__marker"
        :style="{ top: markerTop + 'px', height: '2.6rem' }"
        v-show="items && items.length && activeIndex >= 0"
      ></div>
      <transition-group name="fade" tag="ul" ref="list">
        <li
          v-for="(b, i) in items"
          :key="b.id"
          class="reorder__item"
          :ref="'item-' + i"
          @click="emitFocus(b, i)"
        >
          <span class="reorder__handle" aria-hidden="true">⋮</span>
          <div class="reorder__meta">
            <div class="reorder__title">{{ i + 1 }}. {{ titleOf(b) }}</div>
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
      items: { type: Array, required: true },
      activeIndex: { type: Number, default: -1 }
    },
    data() {
      return {
        markerTop: 0,
        markerHeight: 0
      }
    },
    mounted() {
      this.updateMarker()
      this._ro = new (window.ResizeObserver ||
        class {
          observe() {}
          disconnect() {}
        })(entries => {
        this.updateMarker()
      })
      if (this.$refs.list && this.$refs.list.$el) {
        this._ro.observe(this.$refs.list.$el)
      }
    },
    beforeDestroy() {
      if (this._ro) this._ro.disconnect()
    },
    watch: {
      activeIndex() {
        this.updateMarker()
      },
      items: {
        handler() {
          this.updateMarker()
        },
        deep: true
      }
    },
    methods: {
      titleOf(b) {
        return (
          b.title ||
          b.label ||
          b.component ||
          (this.$t && this.$t('fields.block-editor.block', 'Block')) ||
          'Block'
        )
      },
      emitFocus(b, i) {
        if (!b) return
        this.$emit('focus', { id: b.id, index: i })
      },
      onStart(evt) {
        // Focus the item you just picked up to drag
        const idx = evt && typeof evt.oldIndex === 'number' ? evt.oldIndex : -1
        const b = this.items[idx]
        if (b) this.$emit('focus', { id: b.id, index: idx })
      },
      onEnd(evt) {
        const { oldIndex, newIndex } = evt || {}
        if (oldIndex === newIndex || oldIndex == null || newIndex == null)
          return
        this.$emit('reorder', { oldIndex, newIndex })
        // After reordering, focus the item in its new spot
        const b = this.items[newIndex]
        if (b) this.$emit('focus', { id: b.id, index: newIndex })
      },
      updateMarker() {
        if (this.activeIndex < 0) return
        this.$nextTick(() => {
          const refEntry = this.$refs['item-' + this.activeIndex]
          const el = Array.isArray(refEntry) ? refEntry[0] : refEntry
          if (!el) return
          this.markerTop = el.offsetTop
        })
      }
    }
  }
</script>

<style scoped lang="scss">
  .reorder__list {
    list-style: none;
    margin: 0;
    padding: 0;
    position: relative; /* anchor for absolute marker */
  }
  /* Sliding highlight marker */
  .reorder__marker {
    position: absolute;
    left: 0;
    right: 0;
    background: rgba(100, 150, 255, 0.12); /* subtle highlight */
    border-left: 3px solid rgba(100, 150, 255, 0.9);
    pointer-events: none;
    transition: top 0.18s ease, height 0.18s ease;
    z-index: 0;
  }
  .reorder__item {
    display: flex;
    align-items: center;
    padding: 10px 12px;
    border-bottom: 1px solid $color__border;
    background: $color__block-bg;
    position: relative;
    z-index: 1;
  }
  .reorder__handle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    cursor: grab;
    user-select: none;
    font-weight: 700;
    margin-right: 8px;
  }
  .reorder__meta {
    min-width: 0;
  }
  .reorder__title {
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .reorder__sub {
    opacity: 0.65;
    font-size: 12px;
  }
  .fade-enter-active,
  .fade-leave-active {
    transition: opacity 0.15s;
  }
  .fade-enter,
  .fade-leave-to {
    opacity: 0;
  }
</style>
