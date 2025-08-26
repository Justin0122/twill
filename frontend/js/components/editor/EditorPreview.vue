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
            :draggable-cancel="
              '.editorPreview__header, .dropdown, [data-action]'
            "
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
              <div class="editorPreview__blockWrap" v-autoheight="{ id: item.iNum }">
                <a17-blockeditor-model
                  v-if="idToBlock[item.i]"
                  :block="idToBlock[item.i]"
                  :editor-name="editorName"
                  v-slot="{ block, isActive, blockIndex, move, remove, edit, unEdit, cloneBlock }"
                >
                  <a17-editor-block-preview
                    :ref="block.id"
                    :block="block"
                    :blockIndex="blockIndex"
                    :blocksLength="blocks.length"
                    :isBlockActive="isActive"
                    :key="block.id"
                    :preview-html="previewHtmlById[String(block.id)] || ''"
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
  import { GridItem, GridLayout } from 'vue-grid-layout'
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
      autoheight: {
        inserted (el, binding, vnode) {
          const ctx = vnode.context
          const idStr = binding && binding.value && binding.value.id
          const id = Number(idStr)
          if (!ctx || !id) return
          const measure = () => {
            const px = Math.ceil(el.scrollHeight || el.getBoundingClientRect().height || 0)
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
    data () {
      return {
        loading: false,
        blockSelectIndex: -1,
        handle: '.editorPreview__handle',
        gridCols: 12,
        gridRowHeight: 80,
        gridMargin: [12, 12],
        defaultBlockH: 3,
        maxAutoRows: 200,
        suppressAutoHeight: false,
        layout: [],
        // eslint-disable-next-line vue/no-reserved-keys
        _previewLayoutApplied: false,
        // eslint-disable-next-line vue/no-reserved-keys
        _suppressBlockWatcher: false,
        previewHtmlById: {},
        // eslint-disable-next-line vue/no-reserved-keys
        _ignoreNextLayoutEvent: false,
      }
    },
    computed: {
      previewClass () {
        const bg = tinyColor(this.bgColor)
        return { 'editorPreview--dark': bg.getBrightness() < 180, 'editorPreview--loading': this.loading }
      },
      previewStyle () { return { 'background-color': this.bgColor } },
      idToBlock () {
        const map = {}
        for (const b of this.blocks) map[String(b.id)] = b
        return map
      }
    },
    methods: {
      _hasContentGrid (b) {
        const g = b?.content?.grid
        return g && Number.isFinite(+g.w) && Number.isFinite(+g.h)
      },
      _toNum (val, fallback) { const n = Number(val); return Number.isFinite(n) ? n : fallback },
      _gridOf (block, idx = 0) {
        const bg = block.grid || {}
        let cg = (block.content && block.content.grid) || {}
        if (typeof cg === 'string') { try { cg = JSON.parse(cg) } catch (e) { cg = {} } }
        const raw = Object.assign({ x: 0, y: Math.floor(idx * this.defaultBlockH), w: this.gridCols, h: this.defaultBlockH }, bg, cg)
        const x = Math.max(0, this._toNum(raw.x, 0))
        const y = Math.max(0, this._toNum(raw.y, Math.floor(idx * this.defaultBlockH)))
        const w = Math.min(this.gridCols, Math.max(1, this._toNum(raw.w, this.gridCols)))
        const h = Math.max(1, this._toNum(raw.h, this.defaultBlockH))
        return { x, y, w, h }
      },
      _rebuildLayoutPreferPreview (previews = []) {
        const idToGrid = new Map()
        previews.forEach(p => {
          const id = String(p.id || p.blockId || p.block?.id)
          let cg = p.content?.grid
          if (typeof cg === 'string') { try { cg = JSON.parse(cg) } catch (e) { cg = null } }
          if (id && cg && Number.isFinite(+cg.w) && Number.isFinite(+cg.h)) idToGrid.set(id, { x:+cg.x||0, y:+cg.y||0, w:+cg.w, h:+cg.h })
        })
        const next = this.blocks.map((b, idx) => {
          const i = String(b.id)
          const g = idToGrid.get(i) || this._gridOf(b, idx)
          return { ...g, i, iNum: b.id }
        }).sort((a, b) => (a.y - b.y) || (a.x - b.x))
        this.layout = next
        this._previewLayoutApplied = true
      },
      buildLayoutFromBlocks () {
        const items = this.blocks.map((b, idx) => {
          const g = this._gridOf(b, idx)
          return { x: g.x, y: g.y, w: g.w, h: g.h, i: String(b.id), iNum: b.id }
        }).sort((a, b) => (a.y !== b.y ? a.y - b.y : a.x - b.x))
        return items
      },
      onBlockContentResize(id, contentPx) {
        if (this.suppressAutoHeight) return
        if (!Number.isFinite(contentPx)) return
        const rowsNeeded = Math.max(
          1,
          Math.min(this.maxAutoRows, Math.ceil(contentPx / this.gridRowHeight))
        )
        const idx = this.layout.findIndex(li => li.i === String(id))
        if (idx === -1) return

        const current = Number(this.layout[idx].h) || 1
        if (rowsNeeded > current) {
          this.$set(this.layout[idx], 'h', rowsNeeded)
        }
      },
      _appendY () {
        if (!this.layout.length) return 0
        return this.layout.reduce((m, it) => Math.max(m, it.y + it.h), 0)
      },
      onAdd (add, edit, evt) {
        const { item } = evt
        const initGrid = { x: 0, y: this._appendY(), w: this.gridCols, h: this.defaultBlockH }
        const block = {
          title: item.getAttribute('data-title'),
          component: item.getAttribute('data-component'),
          icon: item.getAttribute('data-icon'),
          grid: initGrid,
          content: { grid: initGrid }
        }
        const index = Math.max(0, evt.newIndex)
        this.addAndEditBlock(add, edit, { block, index })
        this.$nextTick(() => {
          const newBlock = this.blocks[index]
          if (!newBlock) return
          const idStr = String(newBlock.id)
          if (!this.layout.find(li => li.i === idStr)) this.layout.push({ ...initGrid, i: idStr, iNum: newBlock.id })
          this._selectBlock(null, index)
        })
      },
      onLayoutUpdated: debounce(function (newLayout) {
        if (this._ignoreNextLayoutEvent) return

        // If nothing actually changed, skip committing
        const same = newLayout.length === this.layout.length &&
          newLayout.every((nl, idx) => {
            const ol = this.layout[idx]
            return ol && ol.i === nl.i && ol.x === nl.x && ol.y === nl.y && ol.w === nl.w && ol.h === nl.h
          })
        if (same) return

        this._suppressBlockWatcher = true
        this._previewLayoutApplied = false
        this.layout = newLayout.map(li => ({ ...li, iNum: Number(li.i) }))
        const idToGrid = new Map(this.layout.map(l => [l.i, { x: l.x, y: l.y, w: l.w, h: l.h }]))
        const updated = this.blocks.map(b => {
          const g = idToGrid.get(String(b.id)) || this._gridOf(b)
          return { ...b, grid: g, content: { ...(b.content || {}), grid: g } }
        })
        this.$store.commit(BLOCKS.REORDER_BLOCKS, { editorName: this.editorName, value: updated })
        this.$nextTick(() => { this._suppressBlockWatcher = false })
      }, 80),
      _selectBlock (fn = null, index) {
        if (fn) this.selectBlock(fn, index)
        if (this.blockSelectIndex !== index) {
          this.unSubscribe()
          this.blockSelectIndex = index
          this._unSubscribeInternal = this.$store.subscribe(mutation => {
            if (PREVIEW.REFRESH_BLOCK_PREVIEW.includes(mutation.type)) {
              if (PREVIEW.REFRESH_BLOCK_PREVIEW_ALL.includes(mutation.type)) this.getAllPreviews()
              else this.getPreview(index)
            }
          })
        }
      },
      _unselectBlock (fn, index = this.blockSelectIndex) {
        this.unSubscribe()
        this.getPreview(index)
        this.unselectBlock(fn, index)
        this.blockSelectIndex = -1
      },
      _deleteBlock (fn) { this.unSubscribe(); this.deleteBlock(fn) },
      _cloneBlock (fn, index) { this.cloneBlock(fn); this.getPreview(index + 1) },
      unSubscribe () {
        if (!this._unSubscribeInternal) return
        this._unSubscribeInternal()
        this._unSubscribeInternal = null
      },
      _normalizeGrid (raw, idx = 0) {
        const x = Math.max(0, this._toNum(raw?.x, 0))
        const y = Math.max(0, this._toNum(raw?.y, Math.floor(idx * this.defaultBlockH)))
        const w = Math.min(this.gridCols, Math.max(1, this._toNum(raw?.w, this.gridCols)))
        const h = Math.max(1, this._toNum(raw?.h, this.defaultBlockH))
        return { x, y, w, h }
      },

      getAllPreviews () {
        this.loading = true
        this.$store.dispatch(ACTIONS.GET_ALL_PREVIEWS, { editorName: this.editorName })
          .then((previewsMaybe) => {
            const previews = Array.isArray(previewsMaybe) ? previewsMaybe : []
            this.$nextTick(() => {
              // HTML map
              const htmlById = {}
              previews.forEach(p => {
                if (p && (p.id || p.blockId) && typeof p.html === 'string') {
                  htmlById[String(p.id || p.blockId)] = p.html
                }
              })
              this.previewHtmlById = htmlById

              const next = this.blocks.map((b, idx) => {
                const i = String(b.id)
                let cg = previews.find(p => String(p.id) === i)?.content?.grid
                if (typeof cg === 'string') { try { cg = JSON.parse(cg) } catch (e) { cg = null } }
                const g = this._normalizeGrid(cg || this._gridOf(b, idx), idx)
                return { ...g, i, iNum: b.id }
              }).sort((a, b) => (a.y - b.y) || (a.x - b.x))

              this._ignoreNextLayoutEvent = true
              this.layout = next
              this._previewLayoutApplied = true

              this.$nextTick(() => { this._ignoreNextLayoutEvent = false; this.loading = false })
            })
          })
      },
      getPreview (index = -1) {
        this.loading = true
        this.$store.dispatch(ACTIONS.GET_PREVIEW, { editorName: this.editorName, index })
          .then((p) => {
            // update single preview html in map (if action returns it)
            if (p && (p.id || p.blockId) && typeof p.html === 'string') {
              const id = String(p.id || p.blockId)
              // replace object to keep reactivity simple
              this.previewHtmlById = { ...this.previewHtmlById, [id]: p.html }
            }
            this.$nextTick(() => { this.loading = false })
          })
      },
      scrollToActive (target) {
        if (!this.$refs.previewContent) return
        this.$refs.previewContent.scrollTop = Math.max(0, target - 20)
      },
      resizeAllIframes () {
        if (!this.$refs.blockPreview) return
        this.$refs.blockPreview.forEach(preview => { preview.$refs.blockIframe.resize() })
      },
      _resize: debounce(function () { this.resizeAllIframes() }, 200),
      init () { window.addEventListener('resize', this._resize) },
      dispose () { window.removeEventListener('resize', this._resize) }
    },
    mounted () { this.init(); this.$nextTick(this.getAllPreviews) },
    beforeDestroy () { this.dispose() },
    watch: {
      blocks: {
        deep: true,
        handler () {
          if (this._suppressBlockWatcher) return
          const allHaveGrid = this.blocks.length > 0 && this.blocks.every(this._hasContentGrid)
          if (!this._previewLayoutApplied && allHaveGrid) this.layout = this.buildLayoutFromBlocks()
        }
      },
      editorName () {
        this.unSubscribe()
        this._previewLayoutApplied = false
        this.getAllPreviews()
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
    top: 0;
    bottom: 0;
    right: 0;
    left: 0;
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
    top: 0;
    bottom: 0;
    right: 0;
    left: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: inherit;
    background-color: inherit;

    &::after {
      display: block;
      content: '';
      position: absolute;
      top: 20px;
      bottom: 20px;
      right: 20px;
      left: 20px;
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
