<template>
  <div
    class="editorPreview__item"
    :class="previewBlockItemClasses"
    @mousedown.stop
  >
    <div class="editorPreview__frame">
      <a17-editor-iframe
        :block="block"
        @loaded="iframeLoaded"
        ref="blockIframe"
      />
    </div>
    <div
      class="editorPreview__protector editorPreview__dragger"
      @click.prevent="handleBlockPreviewClick"
    ></div>
    <div class="editorPreview__header">
      <a17-buttonbar variant="visible">
        <button type="button" @click="cloneBlock" :title="$t?.('Clone') ?? 'Clone'">
          <span v-svg symbol="clone"></span>
        </button>

        <button
          type="button"
          :disabled="copying || !hasPreviewPayload"
          @click="copyBlock"
          :title="
    !hasPreviewPayload
      ? ($t?.('Generate a preview first') ?? 'Generate a preview first')
      : (copied ? ($t?.('Copied!') ?? 'Copied!') : ($t?.('Copy Preview JSON') ?? 'Copy Preview JSON'))
  "
        >
          <span v-if="!copied" v-svg symbol="copy"></span>
          <span v-else v-svg symbol="check"></span>
        </button>
        <a17-dropdown
          v-if="blocksLength > 1"
          class="f--small"
          position="bottom-left"
          :maxHeight="270"
          ref="blockDropdown"
          @open="handleDropDownOpen"
          @close="handleDropDownClose"
        >
          <button type="button" @click="toggleBlockDropdown(blockIndex)" :title="$t?.('Reorder') ?? 'Reorder'">
            <span v-svg symbol="drag"></span>
          </button>
          <template #dropdown__content>
            <div>
              <button
                type="button"
                v-for="n in blocksLength"
                :key="n"
                @click="moveBlock(n - 1)"
              >
                {{ n }}
              </button>
            </div>
          </template>
        </a17-dropdown>
        <button type="button" @click="deleteBlock" :title="$t?.('Delete') ?? 'Delete'">
          <span v-svg symbol="trash"></span>
        </button>
      </a17-buttonbar>
    </div>
  </div>
</template>

<script>
  /* eslint-disable no-console */
  import EditorIframe from '@/components/editor/EditorIframe.vue'
  import { BlockEditorItemMixin } from '@/mixins'
  import { toRaw } from 'vue'

  export default {
    name: 'A17EditorPreviewBlockItem',
    components: { 'a17-editor-iframe': EditorIframe },
    mixins: [BlockEditorItemMixin],

    props: {
      block: { type: Object, required: true },
      blockIndex: { type: Number, default: 0 },
      blocksLength: { type: Number, default: 0 },
      isBlockActive: { type: Boolean, default: false },
      // IMPORTANT: pass your editor name (usually "default")
      editorName: { type: String, default: 'default' }
    },

    emits: ['scroll-to'],

    data() {
      return {
        dropdownOpen: false,
        copied: false,
        copying: false,
        copyTimer: null
      }
    },

    computed: {
      previewBlockItemClasses() {
        return {
          'editorPreview__item--active': this.isBlockActive,
          'editorPreview__item--dropdown-open': this.dropdownOpen
        }
      },

      // reads the last payload we POSTed for this block.id
      previewPayload() {
        const getter = this.$store.getters['previewPayloads/byBlockId']
        return typeof getter === 'function' ? getter(this.block.id) : null
      },

      hasPreviewPayload() {
        return !!this.previewPayload
      }
    },

    methods: {
      // === Copy the EXACT preview payload ===
      async copyBlock() {
        try {
          this.copying = true

          if (!this.previewPayload) {
            // If someone clicks copy before a preview was ever requested,
            // you can either no-op, show a message, or (optionally) trigger a preview.
            alert('No preview available to copy yet. Generate a preview first.')
            return
          }

          const text = JSON.stringify(this.previewPayload, null, 2)

          if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text)
          } else {
            const ta = document.createElement('textarea')
            ta.value = text
            ta.setAttribute('readonly', '')
            ta.style.position = 'absolute'
            ta.style.left = '-9999px'
            document.body.appendChild(ta)
            ta.select()
            document.execCommand('copy')
            document.body.removeChild(ta)
          }

          this.copied = true
          clearTimeout(this.copyTimer)
          this.copyTimer = setTimeout(() => (this.copied = false), 1500)
        } catch (e) {
          console.error('❌ Copy failed:', e)
          alert('Could not copy block JSON.')
        } finally {
          this.copying = false
        }
      },

      buildPreviewPayloadFromStore(node) {
        const raw = typeof node === 'object' && node ? toRaw(node) : node
        const block = JSON.parse(JSON.stringify(raw || {}))
        const id = block.id
        const type = block.type || block.component
        const editorName = this.editorName

        const getters = this.$store?.getters || {}
        const state = this.$store?.state || {}

        const activeLanguage =
          (typeof getters.activeLanguage === 'function' ? getters.activeLanguage() : null) ||
          getters.activeLanguage ||
          state.language?.active ||
          state.languages?.active ||
          'en'

        let fields = null
        if (typeof getters.fieldsByBlockId === 'function') {
          fields = getters.fieldsByBlockId(id)
        } else if (getters.fieldsByBlockId) {
          fields = getters.fieldsByBlockId[id]
        }

        const content = this.compressFieldsToContent(fields, activeLanguage, id)

        // ===== Medias =====
        let medias = {}
        if (typeof getters.mediasByBlockId === 'function') {
          medias = getters.mediasByBlockId(id) || {}
        } else if (getters.mediasByBlockId) {
          medias = getters.mediasByBlockId[id] || {}
        }

        // ===== Browsers (relations) =====
        let browsers = {}
        if (typeof getters.browsersByBlockId === 'function') {
          browsers = getters.browsersByBlockId(id) || {}
        } else if (getters.browsersByBlockId) {
          browsers = getters.browsersByBlockId[id] || {}
        }

        // ===== Nested children (repeaters + nested blocks) =====
        const blocks = this.buildNestedBlocksForBlock(id, editorName, activeLanguage)

        return {
          id,
          type,
          is_repeater: !!block.is_repeater,
          editor_name: editorName,
          content,
          medias,
          browsers,
          blocks,
          activeLanguage
        }
      },

      /**
       * Debuckets bracketed field names and preserves language maps.
       * Example input (array or map):
       *   name: "blocks[520][theme]" -> "theme"
       *   name: "blocks[520][body][nl]" -> { body: { nl: "<p>..." } }
       */
      compressFieldsToContent(fields, activeLanguage, parentId) {
        if (!fields) return {}

        const stripBucket = (name) => {
          // strip leading "blocks[<id>][" and closing bracket(s)
          // e.g. blocks[520][theme] -> theme
          //      blocks[520][body][nl] -> body][nl   (we handle nesting below)
          const re = new RegExp(`^blocks\\[${String(parentId)}\\]\\[(.+)\\]$`)
          const m = typeof name === 'string' ? name.match(re) : null
          return m ? m[1] : name
        }

        const assignNested = (obj, path, value) => {
          // path like "body][nl" or "group][field][nl"
          // split by "][" safely
          const parts = path.split('][')
          let cur = obj
          for (let i = 0; i < parts.length; i++) {
            const key = parts[i]
            if (i === parts.length - 1) {
              cur[key] = value
            } else {
              if (!cur[key] || typeof cur[key] !== 'object') cur[key] = {}
              cur = cur[key]
            }
          }
        }

        const toValue = (entry) => {
          if (!entry) return null
          if (Object.prototype.hasOwnProperty.call(entry, 'value')) return entry.value
          if (Object.prototype.hasOwnProperty.call(entry, 'values')) return entry.values
          if (Object.prototype.hasOwnProperty.call(entry, 'content')) return entry.content

          if (typeof entry === 'object') {
            const keys = Object.keys(entry)
            const looksLikeLangMap = keys.length && keys.every(k => typeof entry[k] === 'string' || typeof entry[k] === 'object')
            if (looksLikeLangMap && entry[activeLanguage] !== undefined) {
              return entry
            }
          }
          return entry
        }

        const out = {}

        if (Array.isArray(fields)) {
          for (const f of fields) {
            const rawKey = f?.name ?? f?.key ?? f?.field ?? null
            if (!rawKey) continue
            const debucketed = stripBucket(rawKey)

            const val = toValue(f)
            if (typeof debucketed === 'string' && debucketed.includes('][')) {
              assignNested(out, debucketed, val)
            } else {
              out[debucketed] = val
            }
          }
          return out
        }

        if (typeof fields === 'object') {
          for (const [rawKey, v] of Object.entries(fields)) {
            const debucketed = stripBucket(rawKey)
            const val = toValue(v)
            if (typeof debucketed === 'string' && debucketed.includes('][')) {
              assignNested(out, debucketed, val)
            } else {
              out[debucketed] = val
            }
          }
          return out
        }

        return {}
      },

      /**
       * Build nested blocks for a parent by deep-indexing the store
       * and grouping children by repeater/slot key (e.g., "Slides", "button").
       */
      buildNestedBlocksForBlock(parentId, editorName, activeLanguage) {
        const index = this.indexBlocksFromState()
        const children = index.byParent[parentId] || []
        if (!children.length) {
          console.log('🔎 no children for parent', parentId)
          return {}
        }

        const grouped = {}
        for (const child of children) {
          const groupKey = this.deriveGroupKey(child)
          if (!groupKey) {
            console.warn('⚠️ could not derive group key for child', child)
            continue
          }
          if (!grouped[groupKey]) grouped[groupKey] = []
          grouped[groupKey].push(this.buildPreviewPayloadFromStore(child))
        }

        console.log('📦 grouped nested blocks for parent', parentId, grouped)
        return grouped
      },

      /**
       * Deep scan store.state and build:
       *  - byId: { <id>: { id, type, ... } }
       *  - byParent: { <parentId>: [ childObjects ] }
       * We try multiple common parent keys; adapt/add if your project uses other names.
       */
      indexBlocksFromState() {
        const state = this.$store?.state
        const byId = {}
        const byParent = {}

        const getParentId = (obj) => {
          // Try known keys
          const keys = Object.keys(obj)
          // Direct candidates
          for (const k of ['parentId', 'parent_id', 'block_parent_id', 'parent', 'repeater_id', 'repeatable_id', 'parentBlockId']) {
            if (obj[k] != null && Number.isFinite(+obj[k])) return +obj[k]
          }
          // Heuristic: any numeric property whose key contains 'parent'
          for (const k of keys) {
            if (/parent/i.test(k) && Number.isFinite(+obj[k])) return +obj[k]
          }
          return null
        }

        const stack = [state]
        while (stack.length) {
          const cur = stack.pop()
          if (!cur || typeof cur !== 'object') continue

          const isBlockLike =
            Object.prototype.hasOwnProperty.call(cur, 'id') &&
            (Object.prototype.hasOwnProperty.call(cur, 'type') || Object.prototype.hasOwnProperty.call(cur, 'component'))

          if (isBlockLike && Number.isFinite(+cur.id)) {
            const id = +cur.id
            const type = cur.type || cur.component || null
            const parent = getParentId(cur)
            const clone = JSON.parse(JSON.stringify({ ...cur, id, type }))

            byId[id] = clone
            if (parent != null) {
              if (!byParent[parent]) byParent[parent] = []
              byParent[parent].push(clone)
            }
          }

          if (Array.isArray(cur)) stack.push(...cur)
          else stack.push(...Object.values(cur))
        }

        console.log('📚 indexBlocksFromState built:', {
          ids: Object.keys(byId).length,
          parents: Object.keys(byParent).length
        })
        return { byId, byParent }
      },

      /**
       * Derive grouping key:
       *  - "dynamic-repeater-<Name>" -> <Name>
       *  - zone/slot/group/name if present
       *  - fallback: last type segment in lowercase
       */
      deriveGroupKey(child) {
        const t = child.type || child.component || ''
        const m = /^dynamic-repeater-([A-Za-z0-9_-]+)$/.exec(t)
        if (m && m[1]) return m[1]

        const key = child.zone || child.slot || child.group || child.name || null
        if (key) return key

        if (t) {
          const last = t.split('-').pop()
          if (last) return last.toLowerCase()
        }
        return null
      },

      // === UI helpers (unchanged) ===
      handleBlockPreviewClick() {
        if (this.isBlockActive) this.unselectBlock()
        else this.selectBlock()
      },
      handleDropDownOpen() {
        this.dropdownOpen = true
      },
      handleDropDownClose() {
        this.dropdownOpen = false
      },
      iframeLoaded() {
        if (!this.isBlockActive) return
        this.$nextTick(() => this.$emit('scroll-to', this.$el.offsetTop))
      }
    },
    beforeUnmount() {
      clearTimeout(this.copyTimer)
      this.unselectBlock()
    }
  }
</script>

<style lang="scss" scoped>
  @import '~styles/setup/_mixins-colors-vars.scss';

  .editorPreview__item {
    min-height: 80px;
    position: relative;
    margin-bottom: 1px;
    z-index: 1;

    &::after {
      content: '';
      border-radius: 2px;
      position: absolute;
      top: 0;
      right: 0;
      left: 0;
      bottom: 0;
      border: 1px solid $color__border;
      z-index: 0;
      opacity: 0;
    }
  }

  .editorPreview__item:hover::after { border-color: $color__border; opacity: 1; }
  .editorPreview__item--dropdown-open { z-index: 2; }

  .editorPreview__item--active::after,
  .editorPreview__item--active:hover::after { border-color: $color_editor--active; opacity: 1; }

  .editorPreview__protector { position: absolute; left: 0; right: 0; top: 0; bottom: 0; cursor: move; z-index: 1; }

  .editorPreview__header { position: absolute; top: 20px; right: 20px; padding: 0; display: none; background-clip: padding-box; z-index: 2; }

  .editorPreview__item:hover .editorPreview__header,
  .editorPreview__item--active .editorPreview__header,
  .editorPreview__item--dropdown-open .editorPreview__header { display: flex; }

  .editorPreview__item.sortable-chosen { opacity: 1; }
  .editorPreview__item.sortable-ghost { opacity: 0.25; }

  .editorPreview__header button[disabled] { opacity: .6; cursor: progress; }
</style>
