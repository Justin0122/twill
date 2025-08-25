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
            :layout.sync="layout"
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
              v-for="item in layout"
              :key="item.i"
              :i="item.i"
              :x="item.x"
              :y="item.y"
              :w="item.w"
              :h="item.h"
              :min-w="2"
              :min-h="1"
            >
              <span class="editorPreview__handle" />

              <div
                class="editorPreview__blockWrap"
                v-autoheight="{ id: item.iNum }"
              >
                <a17-blockeditor-model
                  v-if="idToBlock[item.i]"
                  :block="idToBlock[item.i]"
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
                    :key="block.id"
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
      // Observe content size and push auto height into layout.h
      autoheight: {
        inserted(el, binding, vnode) {
          const ctx = vnode.context
          const idStr = binding && binding.value && binding.value.id
          const id = Number(idStr)
          if (!ctx || !id) return

          const measure = () => {
            const px = Math.ceil(
              el.scrollHeight || el.getBoundingClientRect().height || 0
            )
            ctx.onBlockContentResize(id, px)
          }

          ctx.$nextTick(() => requestAnimationFrame(measure))
          const handler = () => requestAnimationFrame(measure)

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
        maxAutoRows: 200,
        suppressAutoHeight: false,

        // The canonical layout array (docs-style)
        layout: []
      }
    },
    computed: {
      previewClass() {
        const bg = tinyColor(this.bgColor)
        return {
          'editorPreview--dark': bg.getBrightness() < 180,
          'editorPreview--loading': this.loading
        }
      },
      previewStyle() {
        return { 'background-color': this.bgColor }
      },
      // map id(string) -> block
      idToBlock() {
        const map = {}
        for (const b of this.blocks) {
          map[String(b.id)] = b
        }
        return map
      }
    },
    methods: {
      // Prefer content.grid, then block.grid, else defaults
      _gridOf(block, idx = 0) {
        const cg = (block.content && block.content.grid) || {}
        const bg = block.grid || {}
        const base = {
          x: 0,
          y: Math.floor(idx * this.defaultBlockH),
          w: this.gridCols,
          h: this.defaultBlockH
        }
        const raw = Object.assign({}, base, cg, bg)
        const toNum = v => (Number.isFinite(v) ? v : 0)
        const x = Math.max(0, toNum(raw.x))
        const y = Math.max(0, toNum(raw.y))
        const w = Math.min(
          this.gridCols,
          Math.max(1, Number.isFinite(raw.w) ? raw.w : this.gridCols)
        )
        const h = Math.max(1, Number.isFinite(raw.h) ? raw.h : this.defaultBlockH)
        return { x, y, w, h }
      },

      // Build the layout array from current blocks (in reading order)
      buildLayoutFromBlocks() {
        const items = this.blocks.map((b, idx) => {
          const g = this._gridOf(b, idx)
          return {
            x: g.x,
            y: g.y,
            w: g.w,
            h: g.h,
            i: String(b.id),
            iNum: b.id // helper for the directive
          }
        })
        // keep reading order stable
        items.sort((a, b) => (a.y !== b.y ? a.y - b.y : a.x - b.x))
        return items
      },

      // Update layout.h for a given block id when content height changes
      onBlockContentResize(id, contentPx) {
        if (this.suppressAutoHeight) return
        if (!Number.isFinite(contentPx)) return
        const rows = Math.max(
          1,
          Math.min(this.maxAutoRows, Math.ceil(contentPx / this.gridRowHeight))
        )
        const iStr = String(id)
        const idx = this.layout.findIndex(li => li.i === iStr)
        if (idx !== -1 && this.layout[idx].h !== rows) {
          // mutate in place so :layout.sync picks it up without re-allocating the array
          this.$set(this.layout[idx], 'h', rows)
        }
      },

      // Determine the y to append a new item at the bottom
      _appendY() {
        if (!this.layout.length) return 0
        const bottom = this.layout.reduce((m, it) => Math.max(m, it.y + it.h), 0)
        return bottom
      },

      // blocks management
      onAdd(add, edit, evt) {
        const { item } = evt
        const initGrid = {
          x: 0,
          y: this._appendY(), // append at bottom
          w: this.gridCols,
          h: this.defaultBlockH
        }
        const block = {
          title: item.getAttribute('data-title'),
          component: item.getAttribute('data-component'),
          icon: item.getAttribute('data-icon'),
          grid: initGrid,
          content: { grid: initGrid }
        }

        const index = Math.max(0, evt.newIndex)
        this.addAndEditBlock(add, edit, { block, index })

        // After the store assigns an id, add to local layout
        this.$nextTick(() => {
          const newBlock = this.blocks[index]
          if (!newBlock) return
          const idStr = String(newBlock.id)
          if (!this.layout.find(li => li.i === idStr)) {
            this.layout.push({ ...initGrid, i: idStr, iNum: newBlock.id })
          }
          this._selectBlock(null, index)
        })
      },

      onLayoutUpdated: debounce(function(newLayout) {
        // Ensure our local layout mirrors the one from the grid
        this.layout = newLayout.map(li => ({ ...li, iNum: Number(li.i) }))

        // Sync back to store: update each block's grid + content.grid
        const idToGrid = new Map(
          this.layout.map(l => [l.i, { x: l.x, y: l.y, w: l.w, h: l.h }])
        )

        const updated = this.blocks
          .map(b => {
            const g = idToGrid.get(String(b.id)) || this._gridOf(b)
            return {
              ...b,
              grid: g,
              content: { ...(b.content || {}), grid: g }
            }
          })
          // reorder blocks by layout reading order so index-based behavior remains sane
          .sort((a, b) => {
            const ga = idToGrid.get(String(a.id)) || this._gridOf(a)
            const gb = idToGrid.get(String(b.id)) || this._gridOf(b)
            return ga.y !== gb.y ? ga.y - gb.y : ga.x - gb.x
          })

        this.$store.commit(BLOCKS.REORDER_BLOCKS, {
          editorName: this.editorName,
          value: updated
        })
      }, 80),

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
          .then(() => this.$nextTick(() => (this.loading = false)))
      },
      getPreview(index = -1) {
        this.loading = true
        this.$store
          .dispatch(ACTIONS.GET_PREVIEW, { editorName: this.editorName, index })
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
      // Hydrate layout from blocks (using content.grid or block.grid)
      this.layout = this.buildLayoutFromBlocks()
      this.init()
      this.$nextTick(this.getAllPreviews)
    },
    beforeDestroy() {
      this.dispose()
    },
    watch: {
      // If the editor switches (or server rehydrates blocks), rebuild layout once
      editorName() {
        this.unSubscribe()
        this.layout = this.buildLayoutFromBlocks()
        this.getAllPreviews()
      },
      // If blocks count changes (add/remove), regenerate layout to include new ids
      blocks(newVal, oldVal) {
        if ((oldVal && oldVal.length) !== (newVal && newVal.length)) {
          this.layout = this.buildLayoutFromBlocks()
        }
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
