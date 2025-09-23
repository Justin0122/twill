<template>
  <div class="editorSidebar__listItems">
    <!-- DRAGGABLE: categories -->
    <draggable
      v-model="categoryOrder"
      :item-key="c => c"
      tag="div"
      class="editorSidebar__categories"
      handle=".editorSidebar__categoryHeader"
      @end="saveOrder"
    >
      <template #item="{ element: category }">
        <div
          v-if="groupedBlocks[category] && groupedBlocks[category].length"
          :key="category"
          class="editorSidebar__category"
        >
          <button
            @click="toggleCollapse(category)"
            class="editorSidebar__categoryHeader"
            type="button"
            :title="`Drag to reorder ${category}`"
          >
            <span class="editorSidebar__categoryTitle">{{ category }}</span>
            <span
              class="editorSidebar__categoryIcon"
              :class="{ 'is-open': !isCollapsed(category) }"
            >
              ▼
            </span>
          </button>

          <!-- eslint-disable vue/no-mutating-props -->
          <transition name="collapse">
            <draggable
              v-show="!isCollapsed(category)"
              class="editorSidebar__blocks"
              :class="[editorSidebarClasses]"
              :style="{ backgroundColor: getCategoryColor(category) }"
              :list="blocks"
              :group="{
                name: 'editorBlocks',
                pull: 'clone',
                put: false
              }"
              handle=".editorSidebar__button"
            >
              <!--eslint-enable-->
              <div
                v-for="block in groupedBlocks[category]"
                :key="block.component"
                class="editorSidebar__button"
                :class="{
                  'editorSidebar__button--full-width': hasOnlyOneBlock(category)
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
          </transition>
        </div>
      </template>
    </draggable>
  </div>
</template>

<script>
  import draggable from 'vuedraggable'
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
      storageKey: {
        type: String,
        default: DEFAULT_STORAGE_KEY
      }
    },
    mixins: [DraggableMixin],
    components: { draggable },
    data() {
      return {
        collapsedCategories: {},
        categoryOrder: [] // persisted order of category names
      }
    },
    computed: {
      groupedBlocks() {
        // Group by first word of title
        return this.blocks.reduce((acc, block) => {
          const category = block.title.split(' ')[0]
          if (!acc[category]) acc[category] = []
          acc[category].push(block)
          return acc
        }, {})
      },
      categoriesFromData() {
        return Object.keys(this.groupedBlocks)
      },
      hasOnlyOneBlock() {
        return category => (this.groupedBlocks[category] || []).length === 1
      },
      editorSidebarClasses() {
        return {
          'editorSidebar__blocks--in-fieldset': this.inFieldset
        }
      }
    },
    watch: {
      groupedBlocks: {
        immediate: true,
        handler() {
          this.reconcileOrder()
        }
      },
      // Persist collapsed state any time it changes
      collapsedCategories: {
        deep: true,
        handler() {
          this.saveCollapsed()
        }
      }
    },
    created() {
      this.loadLayout()
    },
    methods: {
      // ---------- Icons ----------
      iconSymbol(icon) {
        return this.hasLgIconVariation(icon) ? `${icon}-lg` : icon
      },
      hasLgIconVariation(icon) {
        return Boolean(document.querySelector(`#icon--${icon}-lg`))
      },

      // ---------- Collapse ----------
      toggleCollapse(category) {
        this.$set(
          this.collapsedCategories,
          category,
          !this.isCollapsed(category)
        )
      },
      isCollapsed(category) {
        return !!this.collapsedCategories[category]
      },

      // ---------- Colors ----------
      getCategoryColor(category) {
        const hash = category.split('').reduce((acc, char) => {
          return char.charCodeAt(0) + ((acc << 5) - acc)
        }, 0)
        return tinycolor({
          h: hash % 360,
          s: 30,
          l: 97
        }).toHexString()
      },

      // ---------- Persistence ----------
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
        this.collapsedCategories =
          typeof collapsed === 'object' && collapsed ? collapsed : {}
      },
      saveOrder() {
        const saved = this.storageRead()
        this.storageWrite({
          ...saved,
          order: this.categoryOrder
        })
      },
      saveCollapsed() {
        const saved = this.storageRead()
        this.storageWrite({
          ...saved,
          collapsed: this.collapsedCategories
        })
      },

      // Ensure saved order matches current categories (add new, drop missing)
      reconcileOrder() {
        const current = this.categoriesFromData
        if (!this.categoryOrder.length) {
          this.categoryOrder = current.slice()
          this.saveOrder()
          return
        }
        // Keep only existing categories
        const kept = this.categoryOrder.filter(c => current.includes(c))
        // Append any new categories not yet saved
        const missing = current.filter(c => !kept.includes(c))
        const next = [...kept, ...missing]
        // Only write if changed to avoid churn
        if (JSON.stringify(next) !== JSON.stringify(this.categoryOrder)) {
          this.categoryOrder = next
          this.saveOrder()
        }
        // Drop collapsed flags for categories that no longer exist
        const collapsedClean = {}
        for (const c of current) {
          if (this.collapsedCategories.hasOwnProperty(c)) {
            collapsedClean[c] = this.collapsedCategories[c]
          }
        }
        if (
          JSON.stringify(collapsedClean) !==
          JSON.stringify(this.collapsedCategories)
        ) {
          this.collapsedCategories = collapsedClean
          this.saveCollapsed()
        }
      }
    }
  }
</script>

<style lang="scss" scoped>
  @import '~styles/setup/_mixins-colors-vars.scss';

  .editorSidebar__categories {
    display: block; // container for draggable items
  }

  .editorSidebar__category {
    margin-bottom: 15px;
  }

  .editorSidebar__categoryHeader {
    @include btn-reset;
    width: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    background: $color__background;
    border-radius: $border-radius;
    border: 1px solid $color__border;
    cursor: move; /* handle for category drag */

    &:hover {
      border-color: $color__border--focus;
    }
  }

  .editorSidebar__categoryTitle {
    font-weight: 600;
    color: $color__text;
  }

  .editorSidebar__categoryIcon {
    font-size: 10px;
    transition: transform 0.2s ease;
    color: $color__text--light;

    &.is-open {
      transform: rotate(180deg);
    }
  }

  .collapse-enter-active,
  .collapse-leave-active {
    transition: all 0.3s ease-out;
    max-height: 1000px;
    overflow: hidden;
  }

  .collapse-enter,
  .collapse-leave-to {
    max-height: 0;
    opacity: 0;
    padding: 0;
    margin: 0;
    border: none;
  }

  .editorSidebar__blocks {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    transition: all 0.3s ease-out;
    max-height: 1000px;
    width: -moz-available;
    width: -webkit-fill-available;
    width: stretch;
    border-radius: $border-radius;
    padding: 10px;
    border: 1px solid rgba(0, 0, 0, 0.05);
    overflow: hidden;
  }

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

    &--full-width {
      width: -moz-available;
      width: -webkit-fill-available;
      width: stretch;
    }

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
      width: 100%;
    }
  }
</style>
