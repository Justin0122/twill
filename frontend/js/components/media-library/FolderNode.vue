<template>
  <div class="folder-node" :class="{ 'is-root': level === 0 }" role="treeitem" :aria-level="level + 1">
    <div class="folder-node__row"
         :data-id="(node.id ?? 'root') + ''"
         :class="{ 'is-active': isActiveHere, 'is-dragover': draggingOver }"
         :style="{ paddingLeft: (level * 14) + 'px' }"
         @dragenter.stop.prevent="onDragEnter"
         @dragover.stop.prevent="onDragOver"
         @dragleave.stop="onDragLeave"
         @drop.stop.prevent="onDrop">
      <button class="folder-node__toggle" v-if="node.children && node.children.length"
              @click="toggleOpen" :aria-expanded="open.toString()">
        <span v-if="open">▾</span><span v-else>▸</span>
      </button>
      <button class="folder-node__name" :class="{ 'is-active': isActiveHere }" @click="selectSelf">
        <span v-if="level===0">All</span>
        <span v-else>{{ node.name }}</span>
      </button>

      <div class="folder-node__actions">
        <button class="folder-node__action" title="New subfolder" @click="createHere">＋</button>
        <button v-if="level>0" class="folder-node__action" title="Rename folder" @click="renameHere">✎</button>
        <button v-if="level>0" class="folder-node__action danger" title="Delete folder" @click="$emit('delete', { id: node.id, path: pathHere() })">🗑</button>
      </div>
    </div>

    <div v-show="open" class="folder-node__children">
      <folder-node v-for="child in node.children"
                   :key="child.id || child.name"
                   :node="child"
                   :level="level+1"
                   :active-path="activePath"
                   :active-id="activeId"
                   @select="$emit('select', $event)"
                   @create="$emit('create', $event)"
                   @rename="$emit('rename', $event)"
                   @delete="$emit('delete', $event)"
                   @move="$emit('move', $event)"
      />
    </div>
  </div>
</template>
<script>
  export default {
    name: 'folder-node',
    props: {
      node: { type: Object, required: true }, // { id, name, path, children: [] }, root: { id:null, name:'', path:'' }
      level: { type: Number, default: 0 },
      activePath: { type: Array, default: () => [] },
      activeId: { type: [Number, String, null], default: null }
    },
    data() {
      // eslint-disable-next-line vue/no-reserved-keys
      return { open: false, draggingOver: false, _dragDepth: 0 }
    },
    created() {
      // Single-active: listen for broadcasted hover changes
      this._onHoverId = id => {
        const myId = (this.node.id ?? 'root') + ''
        this.draggingOver = id !== null && id === myId
        if (!this.draggingOver) this._dragDepth = 0
      }
      this._onHoverClear = () => {
        this.draggingOver = false
        this._dragDepth = 0
      }
      this.$root.$on('ml:dnd:hover', this._onHoverId)
      this.$root.$on('ml:dnd:hover:clear', this._onHoverClear)
    },
    mounted() {
      this._onGlobalDragEnd = () => this._onHoverClear()
      window.addEventListener('dragend', this._onGlobalDragEnd)
      window.addEventListener('drop', this._onGlobalDragEnd)
    },
    beforeDestroy() {
      this.$root.$off('ml:dnd:hover', this._onHoverId)
      this.$root.$off('ml:dnd:hover:clear', this._onHoverClear)
      window.removeEventListener('dragend', this._onGlobalDragEnd)
      window.removeEventListener('drop', this._onGlobalDragEnd)
    },
    computed: {
      isOnActivePath() {
        const here = this.pathHere()
        return here.every((seg, idx) => this.activePath[idx] === seg)
      },
      shouldBeOpen() {
        return this.level === 0 || this.isOnActivePath
      },
      isActiveHere() {
        return this.node.id !== null && this.node.id === this.activeId
      }
    },
    watch: {
      activePath: {
        handler() {
          if (this.shouldBeOpen) this.open = true
        },
        deep: true,
        immediate: true
      }
    },
    methods: {
      onSelectFolder(payload) {
        this.currentFolderId = payload.id ?? null
        this.currentFolderPath = Array.isArray(payload.path) ? payload.path : []
        this.saveLastFolder()
        this.submitFilter()
      },
      pathHere() {
        const path = []
        let n = this
        while (n && n.node) {
          if (n.level > 0 || n.node.name) path.unshift(n.node.name)
          n = n.$parent
          if (!n || n.$options.name !== 'folder-node') break
        }
        return path
      },
      selectSelf() {
        this.$emit('select', {
          id: this.node.id ?? null,
          path: this.level === 0 ? [] : this.pathHere()
        })
      },
      createHere() {
        this.$emit('create', this.pathHere())
      },
      renameHere() {
        if (this.node.id != null)
          this.$emit('rename', { id: this.node.id, path: this.pathHere() })
      },
      toggleOpen() {
        if (this.shouldBeOpen) {
          this.open = true
          return
        }
        this.open = !this.open
      },
      // --- Drag-and-drop targets (accept moving medias) ---
      onDragEnter(evt) {
        if (!this.hasMediaPayload(evt)) return
        this._dragDepth += 1
        // Announce I am the active hover target (single-active)
        this.$root.$emit('ml:dnd:hover', (this.node.id ?? 'root') + '')
        this.draggingOver = true
        evt.preventDefault()
        evt.stopPropagation()
      },
      onDragOver(evt) {
        if (!this.hasMediaPayload(evt)) return
        evt.dataTransfer.dropEffect = 'move'
        evt.preventDefault()
        evt.stopPropagation()
      },
      onDragLeave(evt) {
        if (this._dragDepth > 0) this._dragDepth -= 1
        if (this._dragDepth === 0) {
          this.draggingOver = false
        }
        evt.stopPropagation()
      },
      onDrop(evt) {
        const payload = this.readMediaPayload(evt)
        this._dragDepth = 0
        this.draggingOver = false
        // Clear any other hovered rows
        this.$root.$emit('ml:dnd:hover:clear')
        if (!payload || !payload.ids || !payload.ids.length) {
          evt.preventDefault()
          evt.stopPropagation()
          return
        }
        const targetPath = this.level === 0 ? [] : this.pathHere()
        const targetId = this.node.id ?? null
        this.$emit('move', {
          targetPath,
          targetId,
          mediaIds: payload.ids,
          type: payload.type || null
        })
        evt.preventDefault()
        evt.stopPropagation()
      },
      hasMediaPayload(evt) {
        try {
          const types = Array.from(evt?.dataTransfer?.types || [])
          return (
            types.includes('application/x-media-ids') ||
            types.includes('text/plain')
          )
        } catch (e) {
          return false
        }
      },
      readMediaPayload(evt) {
        try {
          const raw =
            evt.dataTransfer.getData('application/x-media-ids') ||
            evt.dataTransfer.getData('text/plain')
          return JSON.parse(raw)
        } catch (e) {
          return null
        }
      }
      // ---------------------------------------------------
    },
  }
</script>

<style lang="scss">
  .folder-node__action{
    background: transparent;
    border: 0;
    cursor: pointer;
    padding: 2px 4px;
    color: #999;
    &:hover {
      color: #000;
    }
    &.is-active {
      color: #000;
    }
  }
  .folder-node__action.danger { color: #b00020; }

  .folder-node__row {
    position: relative;
    min-height: 32px;
    padding: 6px 8px;
  }

  .folder-node__row.is-dragover {
    outline: 2px dashed rgba(0, 0, 0, 0.25);
    outline-offset: -2px;
    background: rgba(0, 0, 0, 0.04);
  }

  .folder-node__row.is-dragover * {
    pointer-events: none !important;
  }

  .folder-node__row {
    display: flex;
    align-items: center;
    gap: 6px;
    line-height: 1.8;
    padding: 2px 6px;
  }
  .folder-node__toggle,
  .folder-node__name,
  .folder-node__create {
    background: transparent;
    border: 0;
    cursor: pointer;
    padding: 2px 4px;
  }
  .folder-node__name.is-active {
    font-weight: 600;
    text-decoration: underline;
  }
  .folder-node__children {
    margin-left: 0;
  }
</style>
