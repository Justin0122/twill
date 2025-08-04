<template>
  <div class="editorSidebar__listItems">
    <!-- eslint-disable vue/no-mutating-props -->
    <draggable class="editorSidebar__blocks"
               :class="editorSidebarClasses"
               :list="blocks"
               @change="handleBlocksChange"
               v-bind="{
                    group: {
                      name: 'editorBlocks',
                      pull: 'clone',
                      put: false
                    },
                    handle: '.editorSidebar__button'
                    }">
      <!--eslint-enable-->
      <template v-for="(categoryData, category) in categorizedBlocks">
        <div :key="category"
             class="block-category">
          <div class="category-header" @click="toggleCategory(category)">
            <span class="category-title">{{ category }}</span>
            <span class="category-indicator">{{ isCategoryCollapsed(category) ? '+' : '-' }}</span>
          </div>
          <div class="category-blocks" v-show="!isCategoryCollapsed(category)">
            <div class="editorSidebar__button"
                 v-for="block in categoryData"
                 :key="block.component"
                 :data-title="block.title"
                 :data-icon="block.icon"
                 :data-component="block.component">
              <span v-svg :symbol="iconSymbol(block.icon)"></span>
              <span class="editorSidebar__buttonLabel">{{ block.title }}</span>
            </div>
          </div>
        </div>
      </template>
    </draggable>
  </div>
</template>


<script>
  import draggable from 'vuedraggable'
  import { DraggableMixin } from '@/mixins'

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
      }
    },
    mixins: [DraggableMixin],
    components: {
      draggable
    },
    data() {
      return {
        collapsedCategories: {}
      }
    },
    computed: {
      editorSidebarClasses() {
        return {
          'editorSidebar__blocks--in-fieldset': this.inFieldset
        }
      },
      categorizedBlocks() {
        const categories = {}

        this.blocks.forEach(block => {
          const categoryName = this.getCategoryName(block.title)
          if (!categories[categoryName]) {
            categories[categoryName] = []
          }
          categories[categoryName].push(block)
        })

        return categories
      }
    },
    methods: {
      iconSymbol(icon) {
        return this.hasLgIconVariation(icon) ? `${icon}-lg` : icon
      },
      hasLgIconVariation(icon) {
        return Boolean(document.querySelector(`#icon--${icon}-lg`))
      },
      getCategoryName(title) {
        const words = title.split(' ')
        return words[0]
      },
      toggleCategory(category) {
        this.$set(this.collapsedCategories, category, !this.collapsedCategories[category])
      },
      isCategoryCollapsed(category) {
        return Boolean(this.collapsedCategories[category])
      },
      handleBlocksChange(event) {
        if (event.moved) {
          this.$emit('update:blocks', this.blocks)
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
    flex-direction: column;
    gap: 15px;
  }

  .block-category {
    border-radius: $border-radius;
    border: 1px solid $color__border;
    overflow: hidden;

    .category-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 12px 15px;
      background: $color__background;
      cursor: pointer;

      &:hover {
        background: darken($color__background, 2%);
      }
    }

    .category-title {
      font-weight: 600;
      color: $color__text;
    }

    .category-indicator {
      color: $color__text--light;
      font-size: 1.2em;
      line-height: 1;
    }

    .category-blocks {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      padding: 10px;
      background: white;
    }
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
      width: 100%;
    }
  }
</style>
