<template>
  <div class="editorSidebar__listItems">
    <div v-for="(categoryBlocks, category) in groupedBlocks"
         :key="category"
         x-data="{ open: true }"
         class="editorSidebar__category">
      <button @click="open = !open"
              class="editorSidebar__categoryHeader"
              type="button">
        <span class="editorSidebar__categoryTitle">{{ category }}</span>
        <span class="editorSidebar__categoryIcon"
              :class="{ 'is-open': open }">
          ▼
        </span>
      </button>

      <!-- eslint-disable vue/no-mutating-props -->
      <draggable class="editorSidebar__blocks"
                 :class="[editorSidebarClasses, { 'is-collapsed': !open }]"
                 :list="blocks"
                 :group="{
                   name: 'editorBlocks',
                   pull: 'clone',
                   put: false
                 }"
                 handle=".editorSidebar__button">
        <!--eslint-enable-->
        <div
          v-for="block in categoryBlocks"
          :key="block.component"
          class="editorSidebar__button"
          :data-title="block.title"
          :data-icon="block.icon"
          :data-component="block.component"
        >
          <span v-svg :symbol="iconSymbol(block.icon)"></span>
          <span class="editorSidebar__buttonLabel">{{ block.title }}</span>
        </div>
      </draggable>
    </div>
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
    computed: {
      groupedBlocks() {
        return this.blocks.reduce((acc, block) => {
          const category = block.title.split(' ')[0]
          if (!acc[category]) {
            acc[category] = []
          }
          acc[category].push(block)
          return acc
        }, {})
      },
      editorSidebarClasses() {
        return {
          'editorSidebar__blocks--in-fieldset': this.inFieldset
        }
      }
    },
    methods: {
      iconSymbol(icon) {
        return this.hasLgIconVariation(icon) ? `${icon}-lg` : icon
      },
      hasLgIconVariation(icon) {
        return Boolean(document.querySelector(`#icon--${icon}-lg`))
      }
    }
  }
</script>

<style lang="scss" scoped>
  @import '~styles/setup/_mixins-colors-vars.scss';

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
    margin-bottom: 10px;
    cursor: pointer;

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

  .editorSidebar__blocks {
    transition: max-height 0.3s ease-out;
    overflow: hidden;

    &.is-collapsed {
      max-height: 0;
      margin: 0;
      padding: 0;
    }
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
