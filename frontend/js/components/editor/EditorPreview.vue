<template>
  <a17-blockeditor-model
    :editor-name="editorName"
    v-slot="{ add, edit, unEdit }"
  >
    <div
      class="editorPreview"
      :class="previewClass"
      :style="previewStyle"
      @mousedown="_unselectBlock(unEdit)"
    >
      <div class="editorPreview__empty" v-if="!blocks.length">
        <b>{{
            $trans(
              'previewer.drag-and-drop',
              'Drag and drop content from the left navigation'
            )
          }}</b>
      </div>

      <div class="editorPreview__content" ref="previewContent">
        <draggable
          class="editorPreview__dropzone"
          :value="blocks"
          group="editorBlocks"
          :sort="false"
          :draggable="'.__never__'"
          @add="onAdd(add, edit, $event)"
        >
          <grid-layout
            :layout="gridLayout"
            :col-num="gridCols"
            :row-height="gridRowHeight"
            :is-draggable="true"
            :is-resizable="true"
            :vertical-compact="true"
            :margin="gridMargin"
            :use-css-transforms="true"
            :auto-size="true"
            :is-mirrored="false"
            :prevent-collision="false"
            :draggable-handle="handle"
            :draggable-cancel="'.editorPreview__header, .dropdown, [data-action]'"
            @layout-updated="onLayoutUpdated"
          >
            <grid-item
              v-for="savedBlock in blocks"
              :key="savedBlock.id"
              :i="String(savedBlock.id)"
              :x="(savedBlock.grid && savedBlock.grid.x) || 0"
              :y="(savedBlock.grid && savedBlock.grid.y) || 0"
              :w="(savedBlock.grid && savedBlock.grid.w) || 12"
              :h="(savedBlock.grid && savedBlock.grid.h) || 6"
              :min-w="2"
              :min-h="2"
            >
              <span class="editorPreview__handle" />

              <a17-blockeditor-model
                :block="savedBlock"
                :editor-name="editorName"
                v-slot="{
                  block,
                  isActive,
                  blockIndex,
                  move,
                  remove,
                  edit,
                  unEdit,
                  cloneBlock
                }"
              >
                <a17-editor-block-preview
                  :ref="block.id"
                  :block="block"
                  :blockIndex="blockIndex"
                  :blocksLength="blocks.length"
                  :isBlockActive="isActive"
                  :key="savedBlock.id"
                  @block:select="_selectBlock(edit, blockIndex)"
                  @block:unselect="_unselectBlock(unEdit, blockIndex)"
                  @block:move="move"
                  @block:clone="_cloneBlock(cloneBlock, blockIndex)"
                  @block:delete="_deleteBlock(remove)"
                  @scroll-to="scrollToActive"
                />
              </a17-blockeditor-model>
            </grid-item>
          </grid-layout>
        </draggable>
      </div>

      <a17-spinner v-if="loading" :visible="true">
        {{ $trans('fields.block-editor.loading', 'Loading') }}&hellip;
      </a17-spinner>
    </div>
  </a17-blockeditor-model>
</template>

<script>
  import debounce from 'lodash/debounce'
  import tinyColor from 'tinycolor2'
  import { GridLayout, GridItem } from 'vue-grid-layout'
  import draggable from 'vuedraggable'
  import A17BlockEditorModel from '@/components/blocks/BlockEditorModel'
  import A17EditorBlockPreview from '@/components/editor/EditorPreviewBlockItem'
  import A17Spinner from '@/components/Spinner.vue'
  import { BlockEditorMixin } from '@/mixins'
  import ACTIONS from '@/store/actions/index'
  import { BLOCKS, PREVIEW } from '@/store/mutations/index'

  export default {
    name: 'A17editorPreview',
    props: {
      bgColor: { type: String, default: '#FFFFFF' },
      hasBlockActive: { type: Boolean, default: false }
    },
    mixins: [BlockEditorMixin],
    components: {
      'grid-layout': GridLayout,
      'grid-item': GridItem,
      draggable,
      'a17-editor-block-preview': A17EditorBlockPreview,
      'a17-blockeditor-model': A17BlockEditorModel,
      'a17-spinner': A17Spinner
    },
    data() {
      return {
        loading: false,
        blockSelectIndex: -1,
        handle: '.editorPreview__handle',
        // Grid config
        gridCols: 12,
        gridRowHeight: 80,
        gridMargin: [12, 12]
      }
    },
    computed: {
      previewClass() {
        const bgColorObj = tinyColor(this.bgColor)
        return {
          'editorPreview--dark': bgColorObj.getBrightness() < 180,
          'editorPreview--loading': this.loading
        }
      },
      previewStyle() {
        return { 'background-color': this.bgColor }
      },
      gridLayout() {
        return this.blocks.map((b, idx) => {
          const g = b.grid || {}
          return {
            i: String(b.id),
            x: Number.isFinite(g.x) ? g.x : (idx * 4) % this.gridCols,
            y: Number.isFinite(g.y)
              ? g.y
              : Math.floor((idx * 4) / this.gridCols) * 3,
            w: Number.isFinite(g.w) ? g.w : 4,
            h: Number.isFinite(g.h) ? g.h : 3
          }
        })
      }
    },
    methods: {
      // blocks management
      onAdd(add, edit, evt) {
        const { item } = evt
        const block = {
          title: item.getAttribute('data-title'),
          component: item.getAttribute('data-component'),
          icon: item.getAttribute('data-icon'),
          grid: { x: 0, y: 0, w: 4, h: 3 }
        }

        const index = Math.max(0, evt.newIndex)
        this.addAndEditBlock(add, edit, { block, index })
        this._selectBlock(null, index)
      },

      onLayoutUpdated: debounce(function(newLayout) {
        const idToGrid = new Map(
          newLayout.map(l => [l.i, { x: l.x, y: l.y, w: l.w, h: l.h }])
        )
        const updated = this.blocks
          .map(b => {
            const g = idToGrid.get(String(b.id))
            return g ? { ...b, grid: g } : b
          })
          .sort((a, b) => {
            const ga = a.grid || { x: 0, y: 0 }
            const gb = b.grid || { x: 0, y: 0 }
            return ga.y !== gb.y ? ga.y - gb.y : ga.x - gb.x
          })

        this.$store.commit(BLOCKS.REORDER_BLOCKS, {
          editorName: this.editorName,
          value: updated
        })
      }, 100),

      _selectBlock(fn = null, index) {
        if (fn) this.selectBlock(fn, index)

        if (this.blockSelectIndex !== index) {
          this.unSubscribe()
          this.blockSelectIndex = index
          this._unSubscribeInternal = this.$store.subscribe(mutation => {
            if (PREVIEW.REFRESH_BLOCK_PREVIEW.includes(mutation.type)) {
              if (PREVIEW.REFRESH_BLOCK_PREVIEW_ALL.includes(mutation.type)) {
                this.getAllPreviews()
              } else {
                this.getPreview(index)
              }
            }
          })
        }
      },
      _unselectBlock(fn, index = this.blockSelectIndex) {
        this.unSubscribe()
        this.getPreview(index)
        this.unselectBlock(fn, index)
        this.blockSelectIndex = -1
      },
      _deleteBlock(fn) {
        this.unSubscribe()
        this.deleteBlock(fn)
      },
      _cloneBlock(fn, index) {
        this.cloneBlock(fn)
        this.getPreview(index + 1)
      },
      unSubscribe() {
        if (!this._unSubscribeInternal) return
        this._unSubscribeInternal()
        this._unSubscribeInternal = null
      },

      // Previews management
      getAllPreviews() {
        this.loading = true
        this.$store
          .dispatch(ACTIONS.GET_ALL_PREVIEWS, { editorName: this.editorName })
          // eslint-disable-next-line vue/valid-next-tick
          .then(() => this.$nextTick(() => (this.loading = false)))
      },
      getPreview(index = -1) {
        this.loading = true
        this.$store
          .dispatch(ACTIONS.GET_PREVIEW, { editorName: this.editorName, index })
          // eslint-disable-next-line vue/valid-next-tick
          .then(() => this.$nextTick(() => (this.loading = false)))
      },

      // UI Management
      scrollToActive(target) {
        if (!this.$refs.previewContent) return
        this.$refs.previewContent.scrollTop = Math.max(0, target - 20)
      },
      resizeAllIframes() {
        if (!this.$refs.blockPreview) return
        this.$refs.blockPreview.forEach(preview => {
          preview.$refs.blockIframe.resize()
        })
      },
      _resize: debounce(function() {
        this.resizeAllIframes()
      }, 200),
      init() {
        window.addEventListener('resize', this._resize)
      },
      dispose() {
        window.removeEventListener('resize', this._resize)
      }
    },
    mounted() {
      this.init()
      this.$nextTick(this.getAllPreviews)
    },
    beforeDestroy() {
      this.dispose()
    },
    watch: {
      editorName() {
        this.unSubscribe()
        this.getAllPreviews()
      },
      hasBlockActive(active) {
        if (active) return
        this.unSubscribe()
        this.blockSelectIndex = -1
      }
    }
  }
</script>

<style lang="scss" scoped>
  .editorPreview {
    background-color: inherit;
    color: inherit;
  }

  .editorPreview__content {
    position: absolute;
    top: 0; bottom: 0; right: 0; left: 0;
    padding: 20px;
    overflow-y: auto;
    background-color: inherit;
  }

  .editorPreview__dropzone {
    min-height: 100%;
    display: block;
  }

  .editorPreview__empty {
    position: absolute;
    top: 0; bottom: 0; right: 0; left: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: inherit;
    background-color: inherit;

    &::after {
      display: block;
      content: '';
      position: absolute;
      top: 20px; bottom: 20px; right: 20px; left: 20px;
      border: 1px dashed $color__fborder;
    }

    > * {
      padding: 0 40px;
      @include font-medium;
      line-height: 1.35em;
      text-align: center;
      font-weight: 400;
    }
  }

  .editorPreview__empty + .editorPreview__content {
    background-color: transparent;
  }

  .editorPreview__handle {
    position: absolute;
    height: 10px;
    width: 40px;
    left: 50%;
    top: 50%;
    margin-left: -20px;
    margin-top: -5px;
    cursor: move;
    @include dragGrid($color__drag, $color__block-bg);
  }

  ::v-deep(.vue-grid-item) {
    position: relative;
    display: flex;
    flex-direction: column;
  }

  ::v-deep(.vue-grid-item > div) {
    display: flex;
    flex-direction: column;
    height: 100%;
  }
</style>
