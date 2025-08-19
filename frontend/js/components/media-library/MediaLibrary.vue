<template>
  <a17-modal :title="modalTitle" mode="wide" ref="modal" @open="opened">
    <div
      class="medialibrary"
      :style="{
        '--sidebar-width': sidebarWidth + 'px',
        '--foldertree-width': folderTreeWidth + 'px'
      }"
    >
      <div class="medialibrary__frame">
        <div class="medialibrary__header" ref="form">
          <a17-filter
            @submit="submitFilter"
            :clearOption="true"
            @clear="clearFilters"
          >
            <template #navigation>
              <div class="medialibrary__folders-nav">
                <nav class="breadcrumbs" aria-label="Folder path">
                  <a
                    href="#"
                    @click.prevent="goToRoot"
                    :class="{ 'is-active': currentFolderPath.length === 0 }"
                    >All</a
                  >
                  <span
                    v-for="(seg, i) in currentFolderPath"
                    :key="currentFolderPath.slice(0, i + 1).join('/')"
                  >
                    <span class="sep">/</span>
                    <a
                      href="#"
                      @click.prevent="goToIndex(i)"
                      :class="{
                        'is-active': i === currentFolderPath.length - 1
                      }"
                      >{{ seg }}</a
                    >
                  </span>
                </nav>
              </div>

              <!-- Type tabs -->
              <ul
                class="secondarynav secondarynav--desktop"
                v-if="types.length"
              >
                <li
                  class="secondarynav__item"
                  v-for="navType in types"
                  :key="navType.value"
                  :class="{
                    's--on': type === navType.value,
                    's--disabled': type !== navType.value && strict
                  }"
                >
                  <a href="#" @click.prevent="updateType(navType.value)">
                    <span class="secondarynav__link">{{ navType.text }}</span>
                    <span v-if="navType.total > 0" class="secondarynav__number">
                      ({{ navType.total }})
                    </span>
                  </a>
                </li>
              </ul>

              <div
                class="secondarynav secondarynav--mobile secondarynav--dropdown"
              >
                <a17-dropdown
                  ref="secondaryNavDropdown"
                  position="bottom-left"
                  width="full"
                  :offset="0"
                >
                  <a17-button
                    class="secondarynav__button"
                    variant="dropdown-transparent"
                    size="small"
                    @click="$refs.secondaryNavDropdown.toggle()"
                    v-if="selectedType"
                  >
                    <span class="secondarynav__link">{{
                      selectedType.text
                    }}</span>
                    <span class="secondarynav__number">{{
                      selectedType.total
                    }}</span>
                  </a17-button>
                  <div slot="dropdown__content">
                    <ul>
                      <li
                        v-for="navType in types"
                        :key="navType.value"
                        class="secondarynav__item"
                      >
                        <a href="#" @click.prevent="updateType(navType.value)">
                          <span class="secondarynav__link">{{
                            navType.text
                          }}</span>
                          <span class="secondarynav__number">{{
                            navType.total
                          }}</span>
                        </a>
                      </li>
                    </ul>
                  </div>
                </a17-dropdown>
              </div>
            </template>

            <div slot="hidden-filters">
              <a17-vselect
                class="medialibrary__filter-item"
                ref="filter"
                name="tag"
                :options="tags"
                :placeholder="
                  $trans('media-library.filter-select-label', 'Filter by tag')
                "
                :searchable="true"
                maxHeight="175px"
              />
              <a17-checkbox
                class="medialibrary__filter-item"
                ref="unused"
                name="unused"
                :initial-value="0"
                :value="1"
                :label="
                  $trans(
                    'media-library.unused-filter-label',
                    'Show unused only'
                  )
                "
              />
            </div>
          </a17-filter>
        </div>

        <div class="medialibrary__inner">
          <div class="medialibrary__grid">
            <!-- LEFT: folder tree -->
            <aside
              class="medialibrary__foldertree"
              @dragover.prevent="onFolderTreeDragOver"
              @dragenter.prevent
              @drop.prevent="$root.$emit('ml:dnd:hover:clear')"
            >
              <folder-node
                v-if="folderTree"
                :node="folderTree"
                :active-path="currentFolderPath"
                :active-id="currentFolderId"
                @select="onSelectFolder"
                @create="createFolderAtPath"
                @rename="onRenameFolder"
                @delete="onDeleteFolder"
                @move="onMoveToFolder"
              />
            </aside>

            <div
              class="medialibrary__foldertree-resizer"
              @mousedown="startResizingFolderTree"
            ></div>

            <!-- RIGHT: selected media details -->
            <aside class="medialibrary__sidebar">
              <a17-mediasidebar
                :medias="selectedMedias"
                :authorized="authorized"
                :extraMetadatas="extraMetadatas"
                @clear="clearSelectedMedias"
                @delete="deleteSelectedMedias"
                @tagUpdated="reloadTags"
                :type="currentTypeObject"
                :folder="currentFolderFullPath"
                :translatableMetadatas="translatableMetadatas"
                @triggerMediaReplace="replaceMedia"
                :folder-error="folderDeleteError"
                :folder-error-used="folderDeleteUsed"
                @clearFolderError="
                  () => {
                    folderDeleteError = null
                    folderDeleteUsed = []
                  }
                "
              />
            </aside>

            <!-- FOOTER actions -->
            <footer
              class="medialibrary__footer"
              v-if="selectedMedias.length && showInsert && connector"
            >
              <a17-button
                v-if="canInsert"
                variant="action"
                @click="saveAndClose"
                >{{ btnLabel }}</a17-button
              >
              <a17-button v-else variant="action" :disabled="true">{{
                btnLabel
              }}</a17-button>
            </footer>

            <!-- CENTER: media list + uploader -->
            <div class="medialibrary__list" ref="list">
              <a17-uploader
                ref="uploader"
                v-if="authorized"
                @loaded="addMedia"
                @clear="clearSelectedMedias"
                :type="currentTypeObject"
                :folder="currentFolderFullPath"
                :folder-id="currentFolderId"
              />
              <div
                class="medialibrary__list-items"
                @click.capture="onBlankClickClearSelection"
              >
                <a17-itemlist
                  v-if="type === 'file'"
                  :items="renderedMediaItems"
                  :selected-items="selectedMedias"
                  :used-items="usedMedias"
                  @change="updateSelectedMedias"
                  @shiftChange="updateSelectedMedias"
                />
                <a17-mediagrid
                  v-else
                  :items="renderedMediaItems"
                  :selected-items="selectedMedias"
                  :used-items="usedMedias"
                  @change="updateSelectedMediasSingle"
                  @ctrlChange="updateSelectedMediasCtrl"
                  @shiftChange="updateSelectedMedias"
                />
                <a17-spinner v-if="loading" class="medialibrary__spinner"
                  >Loading&hellip;</a17-spinner
                >
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </a17-modal>
</template>

<script>
  import { mapState } from 'vuex'

  import a17Checkbox from '@/components/Checkbox.vue'
  import a17Spinner from '@/components/Spinner.vue'
  import { MEDIA_LIBRARY, NOTIFICATION } from '@/store/mutations'
  import FormDataAsObj from '@/utils/formDataAsObj.js'
  import scrollToY from '@/utils/scrollToY.js'

  import api from '../../store/api/media-library'
  import a17Filter from '../Filter.vue'
  import a17ItemList from '../ItemList.vue'
  import a17MediaGrid from './MediaGrid.vue'
  import a17MediaSidebar from './MediaSidebar.vue'
  import a17Uploader from './Uploader.vue'

  const FolderNode = {
    name: 'folder-node',
    props: {
      node: { type: Object, required: true }, // { id, name, path, children: [] }, root: { id:null, name:'', path:'' }
      level: { type: Number, default: 0 },
      activePath: { type: Array, default: () => [] },
      activeId: { type: [Number, String, null], default: null }
    },
    data() {
      return {
        open: false,
        draggingOver: false,
        _dragDepth: 0,
        isRenaming: false,
        renameValue: '',
        contextMenu: {
          open: false,
          x: 0,
          y: 0
        }
      }
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
      this._onGlobalCtxClose = () => { this.closeContextMenu() }
      this.$root.$on('ml:ctx:close', this._onGlobalCtxClose)
    },
    mounted() {
      this._onGlobalDragEnd = () => this._onHoverClear()
      window.addEventListener('dragend', this._onGlobalDragEnd)
      window.addEventListener('drop', this._onGlobalDragEnd)

      document.addEventListener('click', this._onDocClick, true)
      document.addEventListener('keydown', this._onDocKeydown, true)
      // close on any scroll (captures inside scrollable panes)
      window.addEventListener('scroll', this.closeContextMenu, true)
    },
    beforeDestroy() {
      this.$root.$off('ml:dnd:hover', this._onHoverId)
      this.$root.$off('ml:dnd:hover:clear', this._onHoverClear)
      this.$root.$off('ml:ctx:close', this._onGlobalCtxClose)

      window.removeEventListener('dragend', this._onGlobalDragEnd)
      window.removeEventListener('drop', this._onGlobalDragEnd)

      document.removeEventListener('click', this._onDocClick, true)
      document.removeEventListener('keydown', this._onDocKeydown, true)
      window.removeEventListener('scroll', this.closeContextMenu, true)
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
      // ---------- context menu ----------
      openContextMenu(evt) {
        // close other menus
        this.$root.$emit('ml:ctx:close')

        // initial position at cursor
        this.contextMenu.open = true
        this.contextMenu.x = evt.clientX
        this.contextMenu.y = evt.clientY

        this.$nextTick(() => {
          const menu = this.$refs.ctxmenu
          if (!menu) return
          const rect = menu.getBoundingClientRect()
          const pad = 8
          let x = this.contextMenu.x
          let y = this.contextMenu.y

          if (rect.right > window.innerWidth - pad) {
            x = Math.max(pad, window.innerWidth - rect.width - pad)
          }
          if (rect.bottom > window.innerHeight - pad) {
            y = Math.max(pad, window.innerHeight - rect.height - pad)
          }
          this.contextMenu.x = x
          this.contextMenu.y = y
        })
      },
      closeContextMenu() {
        this.contextMenu.open = false
      },
      _onDocClick(e) {
        if (!this.contextMenu.open) return
        const menu = this.$refs.ctxmenu
        if (menu && !menu.contains(e.target)) this.closeContextMenu()
      },
      _onDocKeydown(e) {
        if (e.key === 'Escape' && this.contextMenu.open) {
          this.closeContextMenu()
        }
      },

      onCtxNewFolder() {
        this.closeContextMenu()
        this.createHere()
      },
      onCtxRename() {
        this.closeContextMenu()
        this.startInlineRename()
      },
      onCtxDelete() {
        this.closeContextMenu()
        this.$emit('delete', { id: this.node.id, path: this.pathHere() })
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
      },
      startInlineRename() {
        // prevent renaming the virtual root or an id-less node
        if (this.level === 0 || this.node.id == null) return
        this.isRenaming = true
        this.renameValue = this.node.name || ''
        this.$nextTick(() => {
          const el = this.$refs.renameInput
          if (el) { el.focus(); el.select() }
        })
      },
      commitInlineRename() {
        if (!this.isRenaming) return
        const next = (this.renameValue || '').trim()
        this.isRenaming = false
        if (!next || next === this.node.name) return
        // Emit up with the new name; parent should handle API call.
        this.$emit('rename', { id: this.node.id, path: this.pathHere(), name: next })
      },
      cancelInlineRename() {
        this.isRenaming = false
        this.renameValue = ''
      },
      // ---------------------------------------------------
    },
    template: `
      <div class="folder-node" :class="{ 'is-root': level === 0 }" :style="{ '--level': level}" role="treeitem" :aria-level="level + 1">
        <div class="folder-node__row"
             :data-id="(node.id ?? 'root') + ''"
             :class="{ 'is-active': isActiveHere, 'is-dragover': draggingOver }"
             @dragenter.stop.prevent="onDragEnter"
             @dragover.stop.prevent="onDragOver"
             @dragleave.stop="onDragLeave"
             @drop.stop.prevent="onDrop"
             @contextmenu.prevent="openContextMenu">

          <!-- INDENT WRAPPER -->
          <div class="folder-node__indent">
            <button
              class="folder-node__toggle"
              :class="{ 'is-hidden': !(node.children && node.children.length) }"
              @click="toggleOpen"
              :aria-expanded="(node.children && node.children.length ? open : false).toString()"
              :aria-label="open ? 'Collapse folder' : 'Expand folder'">
              <svg v-if="node.children && node.children.length" class="icon icon--chev" viewBox="0 0 24 24" aria-hidden="true">
                <path v-if="open" d="M7 10l5 5 5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path v-else d="M10 7l5 5-5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>

            <span class="folder-node__icon" aria-hidden="true">
          <svg v-if="level === 0" class="icon icon--root" viewBox="0 0 24 24">
            <path d="M3 5h8l2 2h8v12a2 2 0 0 1-2 2H3z" fill="currentColor" opacity="0.15"/>
            <path d="M3 5h7l2 2h9M3 5v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7H12L10 5z" fill="none" stroke="currentColor" stroke-width="1.5"/>
          </svg>
          <svg v-else-if="open" class="icon icon--folder-open" viewBox="0 0 24 24">
            <path d="M3 7h7l2 2h9v2" fill="none" stroke="currentColor" stroke-width="1.5"/>
            <path d="M3 10h18a1 1 0 0 1 .95 1.31l-2.2 6.6A2 2 0 0 1 17.85 19H5.15a2 2 0 0 1-1.9-1.09L1.1 11.4A1 1 0 0 1 2.03 10z" fill="currentColor" opacity="0.15"/>
            <path d="M3 7h7l2 2h9M3 10h18l-2.2 6.6A2 2 0 0 1 17.85 19H5.15A2 2 0 0 1 3.25 17z" fill="none" stroke="currentColor" stroke-width="1.5"/>
          </svg>
          <svg v-else class="icon icon--folder" viewBox="0 0 24 24">
            <path d="M3 7h7l2 2h9v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" fill="currentColor" opacity="0.15"/>
            <path d="M3 7h7l2 2h9M3 7v12a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9H12L10 7z" fill="none" stroke="currentColor" stroke-width="1.5"/>
          </svg>
        </span>

            <template v-if="isRenaming">
              <input
                ref="renameInput"
                class="folder-node__rename-input"
                type="text"
                v-model.trim="renameValue"
                :aria-label="$trans ? $trans('media-library.rename-folder', 'Rename folder') : 'Rename folder'"
                @keydown.enter.stop.prevent="commitInlineRename"
                @keydown.esc.stop.prevent="cancelInlineRename"
                @blur="commitInlineRename"
                @click.stop
              />
            </template>
            <template v-else>
              <button
                class="folder-node__name"
                :class="{ 'is-active': isActiveHere, 'is-locked': level===0 || node.id==null }"
                :aria-selected="isActiveHere.toString()"
                @click="selectSelf"
                @dblclick.stop.prevent="startInlineRename"
                :title="(level===0 || node.id==null) ? '' : 'Double-click to rename'">
                <span v-if="level===0">All</span>
                <span v-else>{{ node.name }}</span>
              </button>
            </template>
          </div>
        </div>

        <div v-show="open" class="folder-node__children" role="group">
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

        <!-- Custom context menu -->
        <div v-if="contextMenu.open"
             ref="ctxmenu"
             class="folder-node__ctxmenu"
             :style="{ left: contextMenu.x + 'px', top: contextMenu.y + 'px' }"
             role="menu"
             @click.stop>
          <button class="ctx-item" role="menuitem" @click="onCtxNewFolder">
            ➕ New subfolder
          </button>
          <button v-if="level>0 && node.id!=null" class="ctx-item" role="menuitem" @click="onCtxRename">
            ✏️ Rename
          </button>
          <button v-if="level>0" class="ctx-item danger" role="menuitem" @click="onCtxDelete">
            🗑 Delete
          </button>
        </div>
      </div>
    `
  }

  export default {
    name: 'A17Medialibrary',
    components: {
      'a17-filter': a17Filter,
      'a17-mediasidebar': a17MediaSidebar,
      'a17-uploader': a17Uploader,
      'a17-mediagrid': a17MediaGrid,
      'a17-itemlist': a17ItemList,
      'a17-spinner': a17Spinner,
      'a17-checkbox': a17Checkbox,
      'folder-node': FolderNode
    },
    props: {
      modalTitlePrefix: {
        type: String,
        default() {
          return this.$trans('media-library.title', 'Media Library')
        }
      },
      btnLabelSingle: {
        type: String,
        default() {
          return this.$trans('media-library.insert', 'Insert')
        }
      },
      btnLabelUpdate: {
        type: String,
        default() {
          return this.$trans('media-library.update', 'Update')
        }
      },
      btnLabelMulti: {
        type: String,
        default() {
          return this.$trans('media-library.insert', 'Insert')
        }
      },
      initialPage: { type: Number, default: 1 },
      authorized: { type: Boolean, default: false },
      showInsert: { type: Boolean, default: true },
      extraMetadatas: {
        type: Array,
        default() {
          return []
        }
      },
      translatableMetadatas: {
        type: Array,
        default() {
          return []
        }
      }
    },
    data() {
      return {
        loading: false,
        maxPage: 20,
        mediaItems: [],
        selectedMedias: [],
        gridHeight: 0,
        page: this.initialPage,
        tags: [],
        lastScrollTop: 0,
        gridLoaded: false,
        folderTree: null,
        currentFolderPath: [],
        currentFolderId: null,
        folderDeleteError: null,
        folderDeleteUsed: [],
        sidebarWidth: 290,
        folderTreeWidth: 400,
        userResizedFolderTree: false,
        folderTreeWidthKey: 'twill:ml:folderTreeWidth'
      }
    },
    mounted() {
      this.restoreFolderTreeWidth()
      this.updateDynamicWidths()
      window.addEventListener('resize', this.updateDynamicWidths)
    },
    beforeDestroy() {
      window.removeEventListener('resize', this.updateDynamicWidths)
    },
    computed: {
      currentFolderFullPath() {
        return this.currentFolderPath.join('/')
      },
      currentFolderLabel() {
        return this.currentFolderPath.length
          ? this.currentFolderPath[this.currentFolderPath.length - 1]
          : 'Home'
      },
      renderedMediaItems() {
        return this.mediaItems.map(item => {
          item.disabled =
            (this.filesizeMax > 0 && item.filesizeInMb > this.filesizeMax) ||
            (this.widthMin > 0 && item.width < this.widthMin) ||
            (this.heightMin > 0 && item.height < this.heightMin)
          return item
        })
      },
      currentTypeObject() {
        return this.types.find(type => type.value === this.type)
      },
      endpoint() {
        return this.currentTypeObject.endpoint
      },
      modalTitle() {
        if (this.connector) {
          if (this.indexToReplace > -1)
            return this.modalTitlePrefix + ' – ' + this.btnLabelUpdate
          return this.selectedMedias.length > 1
            ? this.modalTitlePrefix + ' – ' + this.btnLabelMulti
            : this.modalTitlePrefix + ' – ' + this.btnLabelSingle
        }
        return this.modalTitlePrefix
      },
      btnLabel() {
        let type = this.$trans(
          'media-library.types.single.' + this.type,
          this.type
        )
        if (this.indexToReplace > -1) return this.btnLabelUpdate + ' ' + type
        if (this.selectedMedias.length > 1)
          type = this.$trans(
            'media-library.types.multiple.' + this.type,
            this.type
          )
        return this.btnLabelSingle + ' ' + type
      },
      usedMedias() {
        return this.selected[this.connector] || []
      },
      selectedType() {
        return this.types.filter(t => t.value === this.type)[0]
      },
      canInsert() {
        return !this.selectedMedias.some(
          sMedia => !!this.usedMedias.find(uMedia => uMedia.id === sMedia.id)
        )
      },
      ...mapState({
        connector: state => state.mediaLibrary.connector,
        max: state => state.mediaLibrary.max,
        filesizeMax: state => state.mediaLibrary.filesizeMax,
        widthMin: state => state.mediaLibrary.widthMin,
        heightMin: state => state.mediaLibrary.heightMin,
        type: state => state.mediaLibrary.type,
        types: state => state.mediaLibrary.types,
        strict: state => state.mediaLibrary.strict,
        selected: state => state.mediaLibrary.selected,
        indexToReplace: state => state.mediaLibrary.indexToReplace
      })
    },
    watch: {
      type() {
        const saved = this.getSavedFolderPath()
        this.currentFolderPath = saved !== null ? saved : []
        this.clearMediaItems()
        this.gridLoaded = false
        this.loadFolderTree()
      }
    },
    methods: {
      saveFolderTreeWidth() {
        try {
          localStorage.setItem(
            this.folderTreeWidthKey,
            String(this.folderTreeWidth)
          )
        } catch (e) {
          /* ignore */
        }
      },
      // restore width
      restoreFolderTreeWidth() {
        try {
          const raw = localStorage.getItem(this.folderTreeWidthKey)
          if (raw != null) {
            const val = parseInt(raw, 10)
            if (!Number.isNaN(val) && val >= 0) {
              this.folderTreeWidth = val
              this.userResizedFolderTree = true // lock in user's preference
            }
          }
        } catch (e) {
          /* ignore */
        }
      },

      // your existing responsive logic, now respectful of user's choice
      updateDynamicWidths() {
        // LEFT folder tree: only set if user hasn't resized
        if (!this.userResizedFolderTree) {
          if (window.innerWidth < 700) {
            this.folderTreeWidth = 0
          } else if (window.innerWidth < 900) {
            this.folderTreeWidth = 200
          } else {
            this.folderTreeWidth = 220
          }
        }

        // RIGHT sidebar remains responsive
        if (window.innerWidth < 550) {
          this.sidebarWidth = 0
        } else if (window.innerWidth < 700) {
          this.sidebarWidth = 200
        } else if (window.innerWidth < 900) {
          this.sidebarWidth = 250
        } else {
          this.sidebarWidth = 290
        }
      },
      startResizingFolderTree(e) {
        e.preventDefault()
        const startX = e.clientX
        const startWidth = this.folderTreeWidth

        const doDrag = moveEvent => {
          const newWidth = Math.max(
            150,
            startWidth + (moveEvent.clientX - startX)
          )
          this.folderTreeWidth = newWidth
          this.userResizedFolderTree = true
        }

        const stopDrag = () => {
          document.removeEventListener('mousemove', doDrag)
          document.removeEventListener('mouseup', stopDrag)
          this.saveFolderTreeWidth() // persist on mouseup
        }

        document.addEventListener('mousemove', doDrag)
        document.addEventListener('mouseup', stopDrag)
      },
      /* ---------- persistence helpers ---------- */
      storageKey() {
        return `twill:ml:lastFolder:${this.endpoint}:${this.type}`
      },
      saveLastFolder() {
        try {
          localStorage.setItem(this.storageKey(), this.currentFolderFullPath)
          localStorage.setItem(
            this.storageKey() + ':id',
            this.currentFolderId ?? ''
          )
        } catch (e) {}
      },
      readCookie(name) {
        const cookies = document.cookie ? document.cookie.split('; ') : []
        const needle = encodeURIComponent(name) + '='
        for (let i = 0; i < cookies.length; i++) {
          if (cookies[i].indexOf(needle) === 0)
            return decodeURIComponent(cookies[i].substring(needle.length))
        }
        return null
      },
      getSavedFolderPath() {
        const key = this.storageKey()
        let raw = null
        try {
          raw = window.localStorage.getItem(key)
        } catch (e) {
          raw = this.readCookie(key)
        }
        if (raw === null) return null
        const path = raw.split('/').filter(Boolean)
        return path.length ? path : []
      },
      /* ---------- /persistence helpers ---------- */

      replaceMedia({ id }) {
        this.$refs.uploader.replaceMedia(id)
      },
      open() {
        this.$refs.modal.open()
      },
      close() {
        this.$refs.modal.hide()
      },
      opened() {
        const saved = this.getSavedFolderPath()
        if (saved !== null) this.currentFolderPath = saved

        if (!this.gridLoaded) this.reloadGrid()
        if (!this.folderTree) this.loadFolderTree()

        this.listenScrollPosition()
        this.selectedMedias = []

        if (this.connector && this.indexToReplace > -1) {
          const mediaInitSelect = this.selected[this.connector][
            this.indexToReplace
          ]
          if (mediaInitSelect) this.selectedMedias.push(mediaInitSelect)
        }
      },
      updateType(newType) {
        if (this.loading || this.strict || this.type === newType) return
        this.$store.commit(MEDIA_LIBRARY.UPDATE_MEDIA_TYPE, newType)
        this.submitFilter()
      },
      addMedia(media) {
        const index = this.mediaItems.findIndex(item => item.id === media.id)
        if (index > -1) {
          for (const mediaRole in this.selected) {
            this.selected[mediaRole].forEach((mediaCrop, index) => {
              if (media.id === mediaCrop.id) {
                const crops = []
                for (const crop in mediaCrop.crops) {
                  crops[crop] = {
                    height:
                      media.height === mediaCrop.height
                        ? mediaCrop.crops[crop].height
                        : media.height,
                    name: crop,
                    width:
                      media.width === mediaCrop.width
                        ? mediaCrop.crops[crop].width
                        : media.width,
                    x:
                      media.width === mediaCrop.width
                        ? mediaCrop.crops[crop].x
                        : 0,
                    y:
                      media.height === mediaCrop.height
                        ? mediaCrop.crops[crop].y
                        : 0
                  }
                }
                this.$store.commit(MEDIA_LIBRARY.UPDATE_MEDIAS, {
                  index,
                  media: {
                    ...media,
                    width:
                      media.width === mediaCrop.width
                        ? mediaCrop.width
                        : media.width,
                    height:
                      media.height === mediaCrop.height
                        ? mediaCrop.height
                        : media.height,
                    crops
                  },
                  mediaRole
                })
              }
            })
          }
          this.$set(this.mediaItems, index, media)
          this.selectedMedias.unshift(media)
        } else {
          this.mediaItems.unshift(media)
          this.$store.commit(
            MEDIA_LIBRARY.INCREMENT_MEDIA_TYPE_TOTAL,
            this.type
          )
          this.updateSelectedMedias(media.id)
        }
      },
      updateSelectedMedias(item, shift = false) {
        const id = item.id
        const alreadySelectedMedia = this.selectedMedias.filter(
          media => media.id === id
        )
        if (alreadySelectedMedia.length === 0) {
          if (this.max === 1) this.clearSelectedMedias()
          if (this.selectedMedias.length >= this.max && this.max > 0) return

          if (shift && this.selectedMedias.length > 0) {
            const lastSelectedMedia = this.selectedMedias[
              this.selectedMedias.length - 1
            ]
            const lastSelectedMediaIndex = this.mediaItems.findIndex(
              media => media.id === lastSelectedMedia.id
            )
            const selectedMediaIndex = this.mediaItems.findIndex(
              media => media.id === id
            )
            if (selectedMediaIndex === -1 && lastSelectedMediaIndex === -1)
              return

            let start = null
            let end = null
            if (lastSelectedMediaIndex < selectedMediaIndex) {
              start = lastSelectedMediaIndex + 1
              end = selectedMediaIndex
            } else {
              start = selectedMediaIndex
              end = lastSelectedMediaIndex - 1
            }
            for (let i = start; i <= end; i++) {
              if (this.selectedMedias.length >= this.max && this.max > 0) break
              const media = this.mediaItems[i]
              if (!this.selectedMedias.find(sMedia => sMedia.id === media.id)) {
                this.selectedMedias.push(media)
              }
            }
          } else {
            this.selectedMedias.push(item)
          }
        } else if (!shift) {
          if (this.max !== 1) {
            this.selectedMedias = this.selectedMedias.filter(
              media => media.id !== id
            )
          } else {
            this.selectedMedias = [item]
          }
        }
      },
      updateSelectedMediasSingle(item) {
        if (
          this.selectedMedias.length === 1 &&
          this.selectedMedias[0].id === item.id
        ) {
          this.clearSelectedMedias()
          return
        }
        this.clearSelectedMedias()
        this.updateSelectedMedias(item, false)
      },
      updateSelectedMediasCtrl(item) {
        const id = item.id
        const idx = this.selectedMedias.findIndex(m => m.id === id)
        if (idx >= 0) {
          this.selectedMedias.splice(idx, 1)
        } else {
          if (this.max === 1) {
            this.updateSelectedMediasSingle(item)
            return
          }
          if (this.selectedMedias.length >= this.max && this.max > 0) return
          this.selectedMedias.push(item)
        }
      },
      onBlankClickClearSelection(e) {
        const inSelectable = e.target.closest('[data-ml-selectable]')
        if (!inSelectable) this.clearSelectedMedias()
      },
      getFormData(form) {
        let data = FormDataAsObj(form)
        if (data) data.page = this.page
        else data = { page: this.page }
        data.type = this.type
        if (Array.isArray(data.unused) && data.unused.length)
          data.unused = data.unused[0]
        data.folder_id = this.currentFolderId ?? '' // '' or null => root
        return data
      },
      clearFilters() {
        const self = this
        if (this.$refs.filter) this.$refs.filter.value = null
        if (this.$refs.unused) {
          const input = this.$refs.unused.$el.querySelector('input')
          input && input.checked && input.click()
        }
        this.$nextTick(function() {
          self.submitFilter()
        })
      },

      // ------- FOLDERS -------
      loadFolderTree() {
        api.getFolders(
          this.endpoint,
          { type: this.type },
          resp => {
            this.folderTree = resp.data.tree || { name: '', children: [] }
          },
          error => {
            this.folderTree = { name: '', children: [] }
            this.$store.commit(NOTIFICATION.SET_NOTIF, {
              message: error.data?.message || 'Unable to load folders',
              variant: 'error'
            })
          }
        )
      },
      onFolderTreeDragOver(e) {
        // Coalesce with rAF to avoid spamming
        if (this._hoverRaf) return
        this._hoverRaf = requestAnimationFrame(() => {
          this._hoverRaf = null
          // Find the row under the pointer
          const el = document.elementFromPoint(e.clientX, e.clientY)
          const row = el && el.closest && el.closest('.folder-node__row')
          if (row && row.dataset && row.dataset.id) {
            this.$root.$emit('ml:dnd:hover', row.dataset.id)
          } else {
            this.$root.$emit('ml:dnd:hover:clear')
          }
        })
      },
      selectFolderPath(pathArray) {
        this.currentFolderPath = Array.isArray(pathArray) ? pathArray : []
        this.saveLastFolder()
        this.submitFilter()
      },
      goToRoot() {
        this.selectFolderPath([])
      },
      goToIndex(i) {
        this.selectFolderPath(this.currentFolderPath.slice(0, i + 1))
      },
      promptNewFolder() {
        const name = window.prompt(
          this.$trans('media-library.new-folder', 'New folder name')
        )
        if (!name) return
        this.createFolderAtPath(this.currentFolderPath, name)
      },
      createFolderAtPath(parentPath, forcedName = null) {
        const name =
          forcedName ||
          window.prompt(
            this.$trans('media-library.new-subfolder', 'New subfolder name')
          )
        if (!name) return
        api.createFolder(
          this.endpoint,
          { type: this.type, parent: (parentPath || []).join('/'), name },
          () => {
            this.submitFilter()
            this.loadFolderTree()
          },
          error => {
            this.$store.commit(NOTIFICATION.SET_NOTIF, {
              message: error.data?.message || 'Unable to create folder',
              variant: 'error'
            })
          }
        )
      },
      onDeleteFolder(payload) {
        const confirmed = window.confirm(
          this.$trans(
            'media-library.delete-folder-confirm',
            'Delete this folder? All unused media inside (and its subfolders) will be deleted. This cannot be undone.'
          )
        )
        if (!confirmed) return

        api.deleteFolder(
          this.endpoint,
          payload.id,
          resp => {
            // clear any previous error
            this.folderDeleteError = null
            this.folderDeleteUsed = []

            if (this.currentFolderId === payload.id) {
              this.currentFolderId = null
              this.currentFolderPath = []
              this.saveLastFolder()
            }
            this.$store.commit(NOTIFICATION.SET_NOTIF, {
              message: this.$trans(
                'media-library.folder-deleted',
                'Folder deleted'
              ),
              variant: 'success'
            })
            this.page = 1
            this.clearMediaItems()
            this.reloadGrid()
            this.loadFolderTree()
          },
          error => {
            // Prefer the detailed 422 from your controller
            if (error?.status === 422 && error?.data) {
              this.folderDeleteError =
                error.data.message ||
                this.$trans(
                  'media-library.folder-delete-failed',
                  'Unable to delete folder'
                )
              this.folderDeleteUsed = Array.isArray(error.data.used)
                ? error.data.used
                : []
            } else {
              this.folderDeleteError = this.$trans(
                'media-library.folder-delete-failed',
                'Unable to delete folder'
              )
              this.folderDeleteUsed = []
            }

            // Optional toast too:
            this.$store.commit(NOTIFICATION.SET_NOTIF, {
              message: this.folderDeleteError,
              variant: 'error'
            })
          }
        )
      },
      onMoveToFolder({ targetId, mediaIds, type }) {
        // Build request to move to targetId (root may be null)
        const body = {
          type: type || this.type,
          targetId, // use id instead of path
          mediaIds
        }

        const refresh = () => {
          this.clearSelectedMedias()
          this.submitFilter()
          if (typeof this.fetchFolders === 'function') this.fetchFolders()
        }

        api.moveToFolder(
          this.endpoint,
          body,
          () => {
            refresh()
          },
          () => {
            // Refresh anyway to keep UI in sync with server
            refresh()
          }
        )
      },
      onSelectFolder(payload) {
        // payload: { id: number|null, path: string[] }
        this.currentFolderId = payload.id ?? null
        this.currentFolderPath = Array.isArray(payload.path) ? payload.path : []
        this.saveLastFolder()
        this.submitFilter()
      },

      // Rename folder
      onRenameFolder(payload) {
        const currentName = payload.path.slice(-1)[0] || ''
        let name = (payload && typeof payload.name === 'string') ? payload.name.trim() : ''

        if (!name) {
          name = window.prompt(
            this.$trans('media-library.rename-folder', 'Rename folder'),
            currentName
          )
          if (!name) return
        }

        api.renameFolder(
          this.endpoint,
          payload.id,
          { type: this.type, name },
          resp => {
            if (this.currentFolderId === payload.id) {
              const newPath = (resp.data.folder.path || '').split('/').filter(Boolean)
              this.currentFolderPath = newPath
            }
            this.loadFolderTree()
            this.$store.commit(NOTIFICATION.SET_NOTIF, {
              message: this.$trans('media-library.renamed', 'Folder renamed'),
              variant: 'success'
            })
          },
          error => {
            this.$store.commit(NOTIFICATION.SET_NOTIF, {
              message: error.data?.message || 'Unable to rename folder',
              variant: 'error'
            })
          }
        )
      },
      // ------- /FOLDERS -------

      clearSelectedMedias() {
        this.selectedMedias.splice(0)
      },
      deleteSelectedMedias(mediasIds) {
        let keepSelectedMedias = []
        if (mediasIds && mediasIds.length !== this.selectedMedias.length) {
          keepSelectedMedias = this.selectedMedias.filter(
            media => !media.deleteUrl
          )
        }
        mediasIds.forEach(() => {
          this.$store.commit(
            MEDIA_LIBRARY.DECREMENT_MEDIA_TYPE_TOTAL,
            this.type
          )
        })
        this.mediaItems = this.mediaItems.filter(
          media =>
            !this.selectedMedias.includes(media) ||
            keepSelectedMedias.includes(media)
        )
        this.selectedMedias = keepSelectedMedias
        if (this.mediaItems.length <= 40) {
          this.reloadGrid()
        }
      },
      clearMediaItems() {
        this.mediaItems.splice(0)
      },
      reloadGrid() {
        this.loading = true
        const form = this.$refs.form
        const formdata = this.getFormData(form)
        api.get(
          this.endpoint,
          formdata,
          resp => {
            resp.data.items.forEach(item => {
              if (!this.mediaItems.find(media => media.id === item.id)) {
                this.mediaItems.push(item)
              }
            })
            this.maxPage = resp.data.maxPage || 1
            this.tags = resp.data.tags || []
            this.$store.commit(MEDIA_LIBRARY.UPDATE_MEDIA_TYPE_TOTAL, {
              type: this.type,
              total: resp.data.total
            })
            this.loading = false
            this.listenScrollPosition()
            this.gridLoaded = true
          },
          error => {
            this.$store.commit(NOTIFICATION.SET_NOTIF, {
              message: error.data.message,
              variant: 'error'
            })
          }
        )
      },
      reloadTags(tags = []) {
        this.tags = tags
      },
      submitFilter() {
        const self = this
        const el = this.$refs.list
        this.page = 1
        this.clearMediaItems()
        this.clearSelectedMedias()
        if (el.scrollTop === 0) {
          self.reloadGrid()
          return
        }
        scrollToY({
          el,
          offset: 0,
          easing: 'easeOut',
          onComplete: function() {
            self.reloadGrid()
          }
        })
      },
      listenScrollPosition() {
        this.$nextTick(function() {
          if (!this.gridLoaded) return
          const list = this.$refs.list
          if (this.gridHeight !== list.scrollHeight) {
            list.addEventListener('scroll', this.scrollToPaginate)
          }
        })
      },
      scrollToPaginate() {
        if (!this.gridLoaded) return
        const list = this.$refs.list
        const offset = 10
        if (
          list.scrollTop > this.lastScrollTop &&
          list.scrollTop + list.offsetHeight > list.scrollHeight - offset
        ) {
          list.removeEventListener('scroll', this.scrollToPaginate)
          if (this.maxPage > this.page) {
            this.page = this.page + 1
            this.reloadGrid()
          } else {
            this.gridHeight = list.scrollHeight
          }
        }
        this.lastScrollTop = list.scrollTop
      },
      saveAndClose() {
        this.$store.commit(MEDIA_LIBRARY.SAVE_MEDIAS, this.selectedMedias)
        this.close()
      }
    }
  }
</script>

<style lang="scss">
  .medialibrary__filter-item {
    .vselect {
      min-width: 200px;
    }
  }
  .medialibrary__filter-item.checkbox {
    margin-top: 8px;
    margin-right: 45px !important;
  }
  .medialibrary__header {
    @include breakpoint(small-) {
      .filter__inner {
        flex-direction: column;
      }
      .filter__search {
        padding-top: 10px;
        display: flex;
      }
      .filter__search input {
        flex-grow: 1;
      }
    }
  }

  // ---- Layout base ----
  .medialibrary {
    display: block;
    width: 100%;
    min-height: 100%;
    padding: 0;
    position: relative;
  }
  .medialibrary__header {
    background: $color__border--light;
    border-bottom: 1px solid $color__border;
    padding: 0 20px;
  }
  .medialibrary__frame {
    position: absolute;
    inset: 0;
    display: flex;
    flex-flow: column nowrap;
  }
  .medialibrary__inner {
    position: relative;
    width: 100%;
    overflow: hidden;
    flex-grow: 1;
  }
  .medialibrary__grid {
    position: relative;
    height: 100%;
  }

  // ---- Left folder tree (dynamic width) ----
  .medialibrary__foldertree {
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    width: var(--foldertree-width);
    max-height: calc(100vh - 220px);
    overflow: auto;
    border-right: 1px solid #eee;
    padding: 8px 0;

    @media screen and (max-width: 700px) {
      display: none;
    }
  }

  // ---- Resize handle for folder tree ----
  .medialibrary__foldertree-resizer {
    position: absolute;
    top: 0;
    left: var(--foldertree-width);
    bottom: 0;
    width: 4px;
    cursor: col-resize;
    background: transparent;
    z-index: 10;

    &:hover {
      background: rgba(0, 0, 0, 0.1);
    }
  }

  // ---- Right sidebar (dynamic width) ----
  .medialibrary__sidebar {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    width: var(--sidebar-width);
    padding: 0 0 80px 0;
    z-index: 75;
    background: $color__border--light;
    overflow: auto;

    @media screen and (max-width: 550px) {
      display: none;
    }
  }

  // ---- Footer (aligns with sidebar) ----
  .medialibrary__footer {
    position: absolute;
    right: 0;
    z-index: 76;
    bottom: 0;
    width: var(--sidebar-width);
    color: $color__text--light;
    padding: 10px;
    overflow: hidden;
    background: $color__border--light;
    border-top: 1px solid $color__border;

    > button {
      display: block;
      width: 100%;
    }
    @media screen and (max-width: 550px) {
      width: 100%;
    }
  }

  // ---- Center list (auto-fits between left & right) ----
  .medialibrary__list {
    margin: 0;
    position: absolute;
    top: 0;
    left: var(--foldertree-width);
    right: var(--sidebar-width);
    bottom: 0;
    overflow: auto;
    padding: 10px;

    @media screen and (max-width: 700px) {
      left: 0;
    }
    @media screen and (max-width: 550px) {
      right: 0;
    }
  }
  .medialibrary__list-items {
    position: relative;
    display: block;
    width: 100%;
    min-height: 100%;
  }

  // ---- Breadcrumbs / nav ----
  .medialibrary__folders-nav {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
  }
  .breadcrumbs a {
    text-decoration: none;
  }
  .breadcrumbs .sep {
    margin: 0 6px;
    color: #999;
  }
  .breadcrumbs .is-active {
    font-weight: 600;
  }
  .ml-2 {
    margin-left: 8px;
  }
  .mt-2 {
    margin-top: 8px;
  }

  // ===============================
  // Folder tree: explorer-like UI
  // ===============================
  .folder-node {
    --row-h: 32px;
    --indent: 18px;
    --gutter: 8px;
    --guide: #d9d9d9;
    --text: #1f2937;
    --muted: #6b7280;
    --hover: #f3f4f6;
    --active: #e5f2ff;
  }

  // Each row spans full width; actions float to the right
  .folder-node__row {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between; // actions on the right
    gap: 6px;
    min-height: var(--row-h);
    padding: 2px var(--pad-x);
    border-radius: 4px;
    color: var(--text);
    &::before {
      content: '';
      position: absolute;
      /* start at THIS level’s indent column */
      left: calc(var(--gutter) + var(--indent) * var(--level));
      top: calc(var(--row-h) / 2);
      /* draw over to the NEXT level’s column */
      width: var(--indent);
      height: calc(var(--row-h) / 2);
      border-left: 1px solid var(--guide);
      border-bottom: 1px solid var(--guide);
      opacity: 0.6;
      pointer-events: none;
    }
    &:hover {
      background: var(--hover);
    }
    &.is-active {
      background: var(--active);
    }

    &.is-dragover {
      outline: 2px dashed #3b82f6;
      outline-offset: -2px;
      background: rgba(59, 130, 246, 0.06);
    }
    &.is-dragover * {
      pointer-events: none !important;
    }
  }

  // suppress elbow at top level (root node container)
  .is-root > .folder-node__row::before {
    display: none;
  }

  // The group that actually indents (caret + icon + name)
  .folder-node__indent {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
    margin-left: calc(var(--indent) * var(--level));
  }

  .folder-node__toggle {
    width: 2.5rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--muted);
    border: 0;
    background: none;
    cursor: pointer;
    margin-right: 2px;
    &.is-hidden {
      visibility: hidden;
      pointer-events: none;
    }
  }
  .folder-node__icon .icon {
    width: 18px;
    height: 18px;
    color: #f59e0b;
  }
  .folder-node__icon .icon--root {
    color: #6366f1;
  }
  .folder-node__name {
    text-align: left;
    width: 100%;
    border: 0;
    background: none;
    cursor: pointer;
    padding: 4px 6px;
    border-radius: 4px;
    color: inherit;
    font-size: 13px;
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    &.is-active {
      font-weight: 600;
    }
  }
  .folder-node__actions {
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  .folder-node__action {
    border: 0;
    background: none;
    padding: 4px;
    border-radius: 4px;
    cursor: pointer;
    color: var(--muted);
  }
  .folder-node__action:hover {
    background: #edf2f7;
    color: #111827;
  }
  .folder-node__action.danger:hover {
    background: #fee2e2;
    color: #b91c1c;
  }
  .folder-node__action .icon {
    width: 16px;
    height: 16px;
    display: block;
    line-height: 0;
  }

  // Vertical guide for nested children block
  .folder-node__children {
    position: relative;
  }
  .folder-node__children::before {
    content: '';
    position: absolute;
    /* trunk sits at the NEXT level’s indent column */
    left: calc(var(--gutter) + var(--indent) * (var(--level) + 1));
    top: 0;
    bottom: 0; /* makes it grow to include all subfolders */
    width: 1px;
    background: var(--guide);
    opacity: 0.4;
    pointer-events: none;
  }
  .folder-node__children:empty::before {
    display: none;
  }
  .folder-node__rename-input {
    font: inherit;
    color: inherit;
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    padding: 2px 6px;
    min-width: 80px;
    max-width: 220px;
    box-shadow: 0 0 0 2px rgba(59,130,246,0.10);
  }
  .folder-node__name.is-locked { cursor: default; }
  .folder-node__ctxmenu {
    position: fixed;
    z-index: 1000;
    min-width: 180px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 6px;
    box-shadow: 0 12px 28px rgba(0,0,0,0.12), 0 2px 6px rgba(0,0,0,0.06);
  }

  .folder-node__ctxmenu .ctx-item {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 8px 10px;
    font: inherit;
    color: #111827;
    background: transparent;
    border: 0;
    text-align: left;
    border-radius: 6px;
    cursor: pointer;

    &:hover { background: #f3f4f6; }
    &.danger { color: #b91c1c; }
    &.danger:hover { background: #fee2e2; }
  }

</style>
