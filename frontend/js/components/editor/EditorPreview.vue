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
        <!-- vuedraggable only as DROP TARGET; no internal sorting -->
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
            @dragstart="suppressAutoHeight = true"
            @dragend="suppressAutoHeight = false"
            @resizestart="suppressAutoHeight = true"
            @resizeend="suppressAutoHeight = false"
          >
            <grid-item
              v-for="savedBlock in blocks"
              :key="savedBlock.id"
              :i="String(savedBlock.id)"
              :x="_gridOf(savedBlock).x"
              :y="_gridOf(savedBlock).y"
              :w="_gridOf(savedBlock).w"
              :h="getItemH(savedBlock)"
              :min-w="2"
              :min-h="1"
            >
              <!-- Drag handle used by :draggable-handle -->
              <span class="editorPreview__handle" />

              <!-- Wrap content so we can observe its INTRINSIC height (not 100%) -->
              <div class="editorPreview__blockWrap" v-autoheight="{ id: savedBlock.id }">
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
              </div>
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
    directives: {
      // Observe content size and notify with the block id
      autoheight: {
        inserted(el, binding, vnode) {
          const ctx = vnode.context
          const id = binding && binding.value && binding.value.id
          if (!ctx || !id) return

          const measure = () => {
            const px = Math.ceil(el.scrollHeight || el.getBoundingClientRect().height || 0)
            ctx.onBlockContentResize(id, px)
          }

          // Initial measure after render
          ctx.$nextTick(() => requestAnimationFrame(measure))

          const handler = () => {
            // Batch via rAF to avoid double layouts
            requestAnimationFrame(measure)
          }

          if (typeof ResizeObserver !== 'undefined') {
            const ro = new ResizeObserver(handler)
            ro.observe(el)
            el.__ro = ro
          } else {
            el.__resizeHandler = handler
            window.addEventListener('resize', handler)
            handler()
          }
        },
        unbind(el) {
          if (el.__ro) {
            el.__ro.disconnect()
            delete el.__ro
          }
          if (el.__resizeHandler) {
            window.removeEventListener('resize', el.__resizeHandler)
            delete el.__resizeHandler
          }
        }
      }
    },
    data() {
      return {
        loading: false,
        blockSelectIndex: -1,
        handle: '.editorPreview__handle',
        // Grid config
        gridCols: 12,
        gridRowHeight: 80,
        gridMargin: [12, 12],
        defaultBlockH: 3,
        maxAutoRows: 200, // safety clamp against runaway sizes
        // auto heights in grid rows, keyed by block id
        autoHeights: {},
        // pause autoheight while dragging/resizing
        suppressAutoHeight: false
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
        // Build layout; let autoHeights override saved sizes; use content.grid as fallback
        return this.blocks.map((b, idx) => {
          const g = this._gridOf(b, idx)
          const autoH = this.autoHeights[b.id]
          return {
            i: String(b.id),
            x: g.x,
            y: g.y,
            w: g.w,
            h: Number.isFinite(autoH) ? autoH : (Number.isFinite(g.h) ? g.h : this.defaultBlockH)
          }
        })
      }
    },
    methods: {
      // Read grid preferring block.grid, falling back to block.content.grid, else sensible defaults
      _gridOf(block, idx = 0) {
        const cg = (block.content && block.content.grid) || {}
        const bg = block.grid || {}
        const g = Object.assign(
          {
            x: 0,
            y: Math.floor(idx * this.defaultBlockH),
            w: this.gridCols,
            h: this.defaultBlockH
          },
          cg,
          bg // block.grid wins over content.grid
        )
        // Coerce numbers & clamp
        const safe = prop => (Number.isFinite(g[prop]) ? g[prop] : (prop === 'w' ? this.gridCols : (prop === 'h' ? this.defaultBlockH : 0)))
        const x = Math.max(0, safe('x'))
        const y = Math.max(0, safe('y'))
        const w = Math.min(this.gridCols, Math.max(1, safe('w')))
        const h = Math.max(1, safe('h'))
        return { x, y, w, h }
      },

      // On first load, hydrate block.grid from content.grid so UI has it at top-level too
      _hydrateGridFromContent() {
        if (!this.blocks || !this.blocks.length) return
        const updated = this.blocks.map((b, idx) => {
          if (b.grid && typeof b.grid === 'object') return b
          const cg = (b.content && b.content.grid) || null
          if (!cg) return b
          const g = this._gridOf({ ...b, grid: cg }, idx)
          return { ...b, grid: g }
        })
        this.$store.commit(BLOCKS.REORDER_BLOCKS, {
          editorName: this.editorName,
          value: updated
        })
      },

      getItemH(block) {
        const g = this._gridOf(block)
        const autoH = this.autoHeights[block.id]
        return Number.isFinite(autoH) ? autoH : (Number.isFinite(g.h) ? g.h : this.defaultBlockH)
      },

      onBlockContentResize(id, contentPx) {
        if (this.suppressAutoHeight) return
        if (!Number.isFinite(contentPx)) return
        const rows = Math.max(1, Math.min(this.maxAutoRows, Math.ceil(contentPx / this.gridRowHeight)))
        if (this.autoHeights[id] !== rows) {
          this.$set(this.autoHeights, id, rows)
        }
      },

      // blocks management
      onAdd(add, edit, evt) {
        const { item } = evt
        const initialGrid = { x: 0, y: 0, w: this.gridCols, h: this.defaultBlockH }
        const block = {
          title: item.getAttribute('data-title'),
          component: item.getAttribute('data-component'),
          icon: item.getAttribute('data-icon'),
          grid: initialGrid,
          content: { ...(this.content || {}), grid: initialGrid } // keep content.grid in sync from the start
        }

        const index = Math.max(0, evt.newIndex)
        this.addAndEditBlock(add, edit, { block, index })
        this._selectBlock(null, index)
      },

      onLayoutUpdated: debounce(function(newLayout) {
        // Map layout back to blocks and reorder by reading order
        const idToGrid = new Map(
          newLayout.map(l => [l.i, { x: l.x, y: l.y, w: l.w, h: l.h }])
        )
        const updated = this.blocks
          .map(b => {
            const g = idToGrid.get(String(b.id))
            const autoH = this.autoHeights[b.id]
            const finalG = g
              ? { ...g, h: Number.isFinite(autoH) ? autoH : g.h }
              : this._gridOf(b)
            return {
              ...b,
              grid: finalG,
              content: { ...(b.content || {}), grid: finalG } // keep content.grid synced too
            }
          })
          .sort((a, b) => {
            const ga = this._gridOf(a)
            const gb = this._gridOf(b)
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
      this._hydrateGridFromContent()
      this.init()
      this.$nextTick(this.getAllPreviews)
    },
    beforeDestroy() {
      this.dispose()
    },
    watch: {
      editorName() {
        this.unSubscribe()
        this._hydrateGridFromContent()
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

  .editorPreview__blockWrap {
    display: block;
    height: auto !important;
    box-sizing: border-box;
  }

  ::v-deep(.vue-grid-item) {
    position: relative;
    display: block;
  }
</style>
