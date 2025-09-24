<template>
  <div class="editorSidebar__listItems">
    <!-- eslint-disable vue/no-mutating-props -->
    <draggable class="editorSidebar__blocks"
               :class="editorSidebarClasses"
               :modelValue="blocks"
               @update:modelValue="blocks = $event"
               :options="{
                    group: {
                      name: 'editorBlocks',
                      pull: 'clone',
                      put: false
                    },
                    handle: '.editorSidebar__button'
                    }"
               @change="saveOrderFromRender">
      <!--eslint-enable-->
      <div
        v-for="cat in renderOrder"
        :key="cat.id"
        class="editorSidebar__category"
      >
        <button
          @click="toggleCollapse(cat.name)"
          class="editorSidebar__categoryHeader"
          type="button"
          :title="`Drag to reorder ${cat.name}`"
        >
          <span class="editorSidebar__categoryTitle">{{ cat.name }}</span>
          <span
            class="editorSidebar__categoryIcon"
            :class="{ 'is-open': !isCollapsed(cat.name) }"
          >▼</span
          >
        </button>

        <transition name="collapse">
          <div v-show="!isCollapsed(cat.name)" class="editorSidebar__panel">
            <draggable
              class="editorSidebar__blocks"
              :class="[editorSidebarClasses]"
              :style="{ backgroundColor: getCategoryColor(cat.name) }"
              :list="blocks"
              :group="{ name: 'editorBlocks', pull: 'clone', put: false }"
              :sort="false"
              handle=".editorSidebar__button"
            >
              <div
                v-for="block in groupedBlocks[cat.name]"
                :key="block.component"
                class="editorSidebar__button"
                :class="{
                  'editorSidebar__button--full-width': hasOnlyOneBlock(cat.name)
                }"
                :data-title="block.title"
                :data-icon="block.icon"
                :data-component="block.component"
              >
                <span v-svg :symbol="iconSymbol(block.icon)"></span>
                <span class="editorSidebar__buttonLabel">{{
                    block.title
                  }}</span>
              </div>
            </draggable>
          </div>
        </transition>
      </div>
    </draggable>
  </div>
</template>

<script>
  import { VueDraggableNext } from 'vue-draggable-next'

  import { DraggableMixin } from '@/mixins'
  import tinycolor from 'tinycolor2'

  const DEFAULT_STORAGE_KEY = 'twill:editorSidebarLayout'

  export default {
    name: 'A17EditorSidebarBlockList',
    props: {
      blocks: {
        type: Array,
        default: () => []
      },
      inFieldset: {
        type: Boolean,
        default: false
      },
      storageKey: { type: String, default: DEFAULT_STORAGE_KEY }
    },
    mixins: [DraggableMixin],
    components: { draggable: VueDraggableNext },
    data() {
      return {
        collapsedCategories: {},
        categoryOrder: [], // persisted names
        renderOrder: [], // [{ id, name }]
        hydrated: false // set true after storage load; blocks may still be async
      }
    },
    computed: {
      categoryDragOptions() {
        return {
          handle: '.editorSidebar__categoryHeader',
          animation: 180,
          easing: 'ease',
          direction: 'vertical',
          swapThreshold: 0.15,
          invertSwap: true,
          fallbackOnBody: true,
          forceFallback: true,
          ghostClass: 'is-ghost',
          chosenClass: 'is-chosen',
          dragClass: 'is-drag'
        }
      },
      groupedBlocks() {
        return this.blocks.reduce((acc, block) => {
          const category = block.title.split(' ')[0]
          if (!acc[category]) acc[category] = []
          acc[category].push(block)
          return acc
        }, {})
      },
      categoriesFromData() {
        return Object.keys(this.groupedBlocks) // names
      },
      hasOnlyOneBlock() {
        return category => (this.groupedBlocks[category] || []).length === 1
      },
      editorSidebarClasses() {
        return { 'editorSidebar__blocks--in-fieldset': this.inFieldset }
      }
    },
    watch: {
      // Important: don't run immediately during creation (when blocks is still empty)
      groupedBlocks: {
        immediate: false,
        handler() {
          this.reconcileOrder()
        }
      },
      collapsedCategories: {
        deep: true,
        handler() {
          this.saveCollapsed()
        }
      }
    },
    created() {
      this.loadLayout() // sets hydrated=true
    },
    mounted() {
      // Run once after mount; if blocks already available, this applies order.
      this.reconcileOrder()
    },
    methods: {
      iconSymbol: function (icon) {
        // Future block editor icons will have two variations: small and large.
        // Small formats will be used by default in the dropdown, and large
        // formats (named with `-lg` suffix) will be used in the sidebar.
        return this.hasLgIconVariation(icon) ? `${icon}-lg` : icon
      },
      hasLgIconVariation: function (icon) {
        return Boolean(document.querySelector(`#icon--${icon}-lg`))
      },

      // ---------- collapse ----------
      toggleCollapse(name) {
        this.$set(this.collapsedCategories, name, !this.isCollapsed(name))
      },
      isCollapsed(name) {
        return !!this.collapsedCategories[name]
      },

      // ---------- colors ----------
      getCategoryColor(name) {
        const hash = name
          .split('')
          .reduce((acc, ch) => ch.charCodeAt(0) + ((acc << 5) - acc), 0)
        return tinycolor({ h: hash % 360, s: 30, l: 97 }).toHexString()
      },

      // ---------- ids & mapping ----------
      catId(name) {
        const slug = String(name)
          .toLowerCase()
          .replace(/\s+/g, '-')
        let h = 0
        for (let i = 0; i < name.length; i++)
          h = ((h << 5) - h + name.charCodeAt(i)) | 0
        return `${slug}__${Math.abs(h)}`
      },
      toObjects(names) {
        return names.map(n => ({ id: this.catId(n), name: n }))
      },
      toNames(objs) {
        return objs.map(o => o.name)
      },

      // ---------- persistence ----------
      storageRead() {
        try {
          return JSON.parse(localStorage.getItem(this.storageKey) || '{}')
        } catch {
          return {}
        }
      },
      storageWrite(payload) {
        localStorage.setItem(this.storageKey, JSON.stringify(payload))
      },
      loadLayout() {
        const { order = [], collapsed = {} } = this.storageRead()
        this.categoryOrder = Array.isArray(order) ? order : []
        this.collapsedCategories = collapsed && typeof collapsed === 'object' ? collapsed : {}
        this.hydrated = true
      },
      saveOrderFromRender() {
        if (!this.hydrated) return
        this.categoryOrder = this.toNames(this.renderOrder)
        const saved = this.storageRead()
        this.storageWrite({ ...saved, order: this.categoryOrder })
      },
      saveCollapsed() {
        if (!this.hydrated) return
        const saved = this.storageRead()
        this.storageWrite({ ...saved, collapsed: this.collapsedCategories })
      },

      // Keep user order; add/remove categories as data changes; compute visible & render objects
      reconcileOrder() {
        if (!this.hydrated) return

        const present = this.categoriesFromData // names available now
        // If blocks haven't populated yet, don't write back; just show saved order in UI if any
        if (present.length === 0) {
          if (this.categoryOrder.length && this.renderOrder.length === 0) {
            this.renderOrder = this.toObjects(this.categoryOrder)
          }
          return
        }

        let base = this.categoryOrder.length
          ? [...this.categoryOrder]
          : [...present]

        // remove names no longer present
        base = base.filter(n => present.includes(n))
        // append new names at the end
        const missing = present.filter(n => !base.includes(n))
        if (missing.length) base.push(...missing)

        // visible = only categories that currently have blocks
        const visibleNames = base.filter(
          n => (this.groupedBlocks[n] || []).length > 0
        )
        const nextObjects = this.toObjects(visibleNames)

        // update renderOrder only if changed (avoid churn)
        if (JSON.stringify(this.renderOrder) !== JSON.stringify(nextObjects)) {
          this.renderOrder = nextObjects
        }

        // Only persist when we actually have present categories (avoid overwriting with [])
        if (JSON.stringify(this.categoryOrder) !== JSON.stringify(base)) {
          this.categoryOrder = base
          const saved = this.storageRead()
          this.storageWrite({ ...saved, order: this.categoryOrder })
        }

        // scrub collapsed keys for removed categories
        const cleaned = {}
        for (const n of present) {
          if (
            Object.prototype.hasOwnProperty.call(this.collapsedCategories, n)
          ) {
            cleaned[n] = this.collapsedCategories[n]
          }
        }
        if (
          JSON.stringify(cleaned) !== JSON.stringify(this.collapsedCategories)
        ) {
          this.collapsedCategories = cleaned
          this.saveCollapsed()
        }
      }
    }
  }
</script>

<style lang="scss" scoped>
  @import '~styles/setup/_mixins-colors-vars.scss';

  .editorSidebar__blocks--in-fieldset {
    padding-top: 20px;

    .editorSidebar__button:last-child {
      padding-bottom: 0;
    }
  }

  .editorSidebar__listItems > div {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
  }

  .editorSidebar__button {
    @include btn-reset;
    @include font-tiny-btn;
    cursor: move;
    display: flex;
    flex-direction: column;
    width: calc(50% - 5px);
    height: 100px;
    padding: 8px 20px;
    margin-bottom: 10px;
    background: $color__background;
    border-radius: $border-radius;
    border: 1px solid $color__border;
    color: $color__text--light;
    text-align: center;

    .icon {
      flex-grow: 1;
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: $color__icons;
    }

    .editorSidebar__buttonLabel {
      width: 100%;
      line-height: 1;
    }

    &:hover,
    &:focus {
      color: $color__text;
      border-color: $color__border--focus;

      .icon {
        color: $color__text;
      }
    }
  }

  .editorPreview__content {
    .editorSidebar__button {
      // use full width instead of half for buttons being dragged to the content area
      width: 100%;
    }
  }
</style>
