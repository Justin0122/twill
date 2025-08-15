<template>
  <a17-modal :title="modalTitle" mode="wide" ref="modal" @open="opened">
    <div class="medialibrary">
      <div class="medialibrary__frame">
        <div class="medialibrary__header" ref="form">
          <a17-filter
            @submit="submitFilter"
            :clearOption="true"
            @clear="clearFilters"
          >
            <template #navigation>
              <!-- Breadcrumbs + quick "New folder" -->
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
                <a17-button
                  size="small"
                  variant="secondary"
                  @click="promptNewFolder"
                >
                  New folder
                </a17-button>
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

                <li class="secondarynav__item secondarynav__item--action">
                  <a17-button
                    size="small"
                    variant="secondary"
                    @click="promptNewFolder"
                  >
                    New folder
                  </a17-button>
                </li>
              </ul>

              <!-- Mobile types dropdown + "New folder" -->
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

                <div class="mt-2">
                  <a17-button
                    size="small"
                    variant="secondary"
                    @click="promptNewFolder"
                  >
                    New folder
                  </a17-button>
                </div>
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
            <aside class="medialibrary__foldertree">
              <folder-node
                v-if="folderTree"
                :node="folderTree"
                :active-path="currentFolderPath"
                :active-id="currentFolderId"
                @select="onSelectFolder"
                @create="createFolderAtPath"
                @rename="onRenameFolder"
                @delete="onDeleteFolder"
              />
            </aside>

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

              <a17-button
                class="ml-2"
                variant="secondary"
                :disabled="!selectedMedias.length"
                @click="moveSelectedToCurrentFolder"
              >
                Move to “{{ currentFolderLabel }}”
              </a17-button>
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
              <div class="medialibrary__list-items">
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
                  @change="updateSelectedMedias"
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
      return { open: false }
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
      }
    },
    template: `
      <div class="folder-node" :class="{ 'is-root': level === 0 }" role="treeitem" :aria-level="level + 1">
        <div class="folder-node__row" :class="{ 'is-active': isActiveHere }" :style="{ paddingLeft: (level * 14) + 'px' }">
          <button class="folder-node__toggle" v-if="node.children && node.children.length"
                  @click="toggleOpen" :aria-expanded="open.toString()">
            <span v-if="open">▾</span><span v-else>▸</span>
          </button>

          <button class="folder-node__name" :class="{ 'is-active': isActiveHere }" @click="selectSelf">
            <span v-if="level===0">All</span>
            <span v-else>{{ node.name }}</span>
          </button>

          <div class="folder-node__actions" v-if="level>0">
            <button class="folder-node__action" title="New subfolder" @click="createHere">＋</button>
            <button class="folder-node__action" title="Rename folder" @click="renameHere">✎</button>
            <button class="folder-node__action danger" title="Delete folder" @click="$emit('delete', { id: node.id, path: pathHere() })">🗑</button>
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
          />
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
      }
    },
    computed: {
      currentFolderFullPath() {
        return this.currentFolderPath.join('/')
      },
      currentFolderLabel() {
        return this.currentFolderPath.length
          ? this.currentFolderPath[this.currentFolderPath.length - 1]
          : 'All'
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
        } catch (e) {
          /* fallback cookie like before */
        }
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
              end = selectedMediaIndex + 1
            } else {
              start = selectedMediaIndex
              end = lastSelectedMediaIndex
            }

            const selectedMedias = this.mediaItems.slice(start, end)
            selectedMedias.forEach(media => {
              if (this.selectedMedias.length >= this.max && this.max > 0) return
              const index = this.selectedMedias.findIndex(
                m => m.id === media.id
              )
              if (index === -1) this.selectedMedias.push(media)
            })
          } else {
            const mediaToSelect = this.mediaItems.filter(
              media => media.id === id
            )
            if (mediaToSelect.length) this.selectedMedias.push(mediaToSelect[0])
          }
        } else {
          this.selectedMedias = this.selectedMedias.filter(
            media => media.id !== id
          )
        }
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
          (resp) => {
            // If we deleted the current folder, bounce back to root
            if (this.currentFolderId === payload.id) {
              this.currentFolderId = null
              this.currentFolderPath = []
              this.saveLastFolder()
            }
            this.$store.commit(NOTIFICATION.SET_NOTIF, {
              message: this.$trans('media-library.folder-deleted', 'Folder deleted'),
              variant: 'success'
            })
            // refresh UI
            this.page = 1
            this.clearMediaItems()
            this.reloadGrid()
            this.loadFolderTree()
          },
          (error) => {
            const msg =
              error?.data?.message ||
              this.$trans('media-library.folder-delete-failed', 'Unable to delete folder')
            this.$store.commit(NOTIFICATION.SET_NOTIF, { message: msg, variant: 'error' })
          }
        )
      },

      onSelectFolder(payload) {
        // payload: { id: number|null, path: string[] }
        this.currentFolderId = payload.id ?? null
        this.currentFolderPath = Array.isArray(payload.path) ? payload.path : []
        this.saveLastFolder() // persists "path"; you can optionally persist id too
        this.submitFilter()
      },

      // Rename folder
      onRenameFolder(payload) {
        // payload: { id, path }
        const currentName = payload.path.slice(-1)[0] || ''
        const name = window.prompt(
          this.$trans('media-library.rename-folder', 'Rename folder'),
          currentName
        )
        if (!name) return

        api.renameFolder(
          this.endpoint,
          payload.id,
          { type: this.type, name },
          resp => {
            // If we renamed the current folder, update breadcrumbs path only (id stays the same)
            if (this.currentFolderId === payload.id) {
              const newPath = (resp.data.folder.path || '')
                .split('/')
                .filter(Boolean)
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
      moveSelectedToCurrentFolder() {
        if (!this.selectedMedias.length) return
        api.moveToFolder(
          this.endpoint,
          {
            type: this.type,
            targetId: this.currentFolderId,
            mediaIds: this.selectedMedias.map(m => m.id)
          },
          () => {
            this.$store.commit(NOTIFICATION.SET_NOTIF, {
              message: this.$trans('media-library.moved', 'Moved to folder'),
              variant: 'success'
            })
            this.page = 1
            this.clearMediaItems()
            this.reloadGrid()
          },
          error => {
            this.$store.commit(NOTIFICATION.SET_NOTIF, {
              message: error.data?.message || 'Unable to move items',
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
  $width_sidebar: (
    default: 290px,
    small: 250px,
    xsmall: 200px
  );

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
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
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

  .medialibrary__foldertree {
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    width: 220px;
    max-height: calc(100vh - 220px);
    overflow: auto;
    border-right: 1px solid #eee;
    padding: 8px 0;
    @media screen and (max-width: 700px) {
      display: none;
    }
  }

  .medialibrary__footer {
    position: absolute;
    right: 0;
    z-index: 76;
    bottom: 0;
    width: map-get($width_sidebar, default);
    color: $color__text--light;
    padding: 10px;
    overflow: hidden;
    background: $color__border--light;
    border-top: 1px solid $color__border;

    > button {
      display: block;
      width: 100%;
    }
    @include breakpoint(small) {
      width: map-get($width_sidebar, small);
    }
    @include breakpoint(xsmall) {
      width: map-get($width_sidebar, xsmall);
    }
    @media screen and (max-width: 550px) {
      width: 100%;
    }
  }

  .medialibrary__sidebar {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    width: map-get($width_sidebar, default);
    padding: 0 0 80px 0;
    z-index: 75;
    background: $color__border--light;
    overflow: auto;

    @include breakpoint(small) {
      width: map-get($width_sidebar, small);
    }
    @include breakpoint(xsmall) {
      width: map-get($width_sidebar, xsmall);
    }
    @media screen and (max-width: 550px) {
      display: none;
    }
  }

  .medialibrary__list {
    margin: 0;
    position: absolute;
    top: 0;
    left: 220px;
    right: map-get($width_sidebar, default);
    bottom: 0;
    overflow: auto;
    padding: 10px;
    @include breakpoint(small) {
      right: map-get($width_sidebar, small);
    }
    @include breakpoint(xsmall) {
      right: map-get($width_sidebar, xsmall);
    }
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
  .folder-node__action.danger { color: #b00020; }
</style>
