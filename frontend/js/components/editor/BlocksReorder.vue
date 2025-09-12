<template>
  <div class="reorder">
    <draggable
      class="reorder__list"
      :value="items"
      :handle="'.reorder__handle'"
      @end="onEnd"
    >
      <transition-group name="fade" tag="ul">
        <li v-for="(b, i) in items" :key="b.id" class="reorder__item">
          <span class="reorder__handle" aria-hidden="true">⋮⋮</span>

          <div class="reorder__meta">
            <div class="reorder__title">
              {{ i + 1 }}. {{ titleOf(b) }}
            </div>

            <div class="reorder__thumbs" v-if="thumbsOf(b).length">
              <img
                v-for="(t, idx) in thumbsOf(b)"
                :key="t.url + '_' + idx"
                class="reorder__thumb"
                :src="t.url"
                :alt="t.alt"
                loading="lazy"
                decoding="async"
              />
            </div>
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

      preferRole: { type: String, default: '' },

      preferThumbKey: { type: String, default: 'admin' },

      /** How many thumbs per block row */
      maxThumbs: { type: Number, default: 3 }
    },
    methods: {
      titleOf(b) {
        if (b && (b.title || b.label || b.component)) return b.title || b.label || b.component
        return (this.$t && this.$t('fields.block-editor.block', 'Block')) || 'Block'
      },

      onEnd(evt) {
        const oldIndex = evt && evt.oldIndex
        const newIndex = evt && evt.newIndex
        if (oldIndex === newIndex || oldIndex == null || newIndex == null) return
        this.$emit('reorder', { oldIndex, newIndex })
      },

      thumbsOf(block) {
        // 1) Standard Twill medias on block / block.content
        const fromMedias = this.extractFromTwillMedias(block)
        if (fromMedias.length) return fromMedias.slice(0, this.maxThumbs)

        // 2) MediaField-like objects (with 'thumbnail' / 'original' / 'crops') in block.content
        const fromMediaField = this.extractFromMediaField(block && block.content)
        if (fromMediaField.length) return fromMediaField.slice(0, this.maxThumbs)

        // 3) Generic deep scan for { url | src } strings (very lax fallback)
        const generic = this.extractGenericUrls(block && block.content)
        return generic.slice(0, this.maxThumbs)
      },

      extractFromTwillMedias(block) {
        const out = []
        const root =
          (block && block.medias) ||
          (block && block.content && block.content.medias) ||
          null

        if (!root || typeof root !== 'object') return out

        const roles = Object.keys(root)
        if (!roles.length) return out

        const orderedRoles =
          this.preferRole && roles.includes(this.preferRole)
            ? [this.preferRole, ...roles.filter(r => r !== this.preferRole)]
            : roles

        for (const role of orderedRoles) {
          const arr = Array.isArray(root[role]) ? root[role] : []
          for (const m of arr) {
            const url =
              (m &&
                m.thumbnails &&
                (m.thumbnails[this.preferThumbKey] ||
                  m.thumbnails.list ||
                  m.thumbnails.admin ||
                  m.thumbnails.default)) ||
              (m && m.preview_url) ||
              (m && m.url) ||
              null

            if (url) {
              out.push({ url, alt: (m && (m.alt || m.name)) || role })
            }
            if (out.length >= this.maxThumbs) return out
          }
          if (out.length >= this.maxThumbs) break
        }
        return out
      },

      // Walk block.content and pick objects that look like MediaField payloads (thumbnail/original/crops)
      extractFromMediaField(content) {
        const out = []
        const visit = (val) => {
          if (!val || out.length >= this.maxThumbs) return
          if (Array.isArray(val)) {
            for (const v of val) {
              if (out.length >= this.maxThumbs) break
              visit(v)
            }
            return
          }
          if (typeof val === 'object') {
            // Shape match for MediaField-ish
            if (
              (typeof val.thumbnail === 'string' && val.thumbnail) ||
              (typeof val.preview_url === 'string' && val.preview_url) ||
              (val.thumbnails && typeof val.thumbnails === 'object')
            ) {
              const candidate =
                (val.thumbnails &&
                  (val.thumbnails[this.preferThumbKey] ||
                    val.thumbnails.list ||
                    val.thumbnails.admin ||
                    val.thumbnails.default)) ||
                val.preview_url ||
                val.thumbnail ||
                val.url ||
                val.src ||
                null
              if (candidate) {
                out.push({ url: candidate, alt: val.alt || val.name || 'image' })
              }
            }

            // Continue scanning nested keys
            for (const k of Object.keys(val)) {
              if (out.length >= this.maxThumbs) break
              visit(val[k])
            }
          }
        }
        visit(content)
        return out
      },

      // Very generic last-resort URL scan (small + safe)
      extractGenericUrls(content) {
        const out = []
        const visit = (val) => {
          if (!val || out.length >= this.maxThumbs) return
          if (Array.isArray(val)) {
            for (const v of val) {
              if (out.length >= this.maxThumbs) break
              visit(v)
            }
            return
          }
          if (typeof val === 'object') {
            // common keys for inline images
            if (typeof val.url === 'string' && this.looksLikeImg(val.url)) {
              out.push({ url: val.url, alt: val.alt || 'image' })
            } else if (typeof val.src === 'string' && this.looksLikeImg(val.src)) {
              out.push({ url: val.src, alt: val.alt || 'image' })
            }
            for (const k of Object.keys(val)) {
              if (out.length >= this.maxThumbs) break
              visit(val[k])
            }
          }
        }
        visit(content)
        return out
      },

      looksLikeImg(url) {
        return /\.(png|jpe?g|gif|webp|avif|svg)(\?.*)?$/i.test(url)
      }
    }
  }
</script>

<style scoped lang="scss">
  .reorder__list { list-style: none; margin: 0; padding: 0; }
  .reorder__item {
    display: flex; align-items: center;
    padding: 10px 12px; border-bottom: 1px solid $color__border;
    background: $color__block-bg;
  }
  .reorder__handle {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; cursor: grab; user-select: none; font-weight: 700;
    margin-right: 8px;
  }
  .reorder__meta { min-width: 0; flex: 1 1 auto; }
  .reorder__title { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

  /* Thumbnails row */
  .reorder__thumbs {
    display: flex; gap: 6px; margin-top: 6px;
  }
  .reorder__thumb {
    width: 36px; height: 36px; object-fit: cover; border-radius: 4px;
    border: 1px solid rgba(0,0,0,.06);
  }

  /* Animations */
  .fade-enter-active, .fade-leave-active { transition: opacity .15s; }
  .fade-enter, .fade-leave-to { opacity: 0; }
</style>
