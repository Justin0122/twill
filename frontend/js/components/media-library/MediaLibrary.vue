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
                      :class="{ 'is-active': i === currentFolderPath.length - 1 }"
                    >{{ seg }}</a
                    >
                  </span>
                </nav>
                <a17-button size="small" variant="secondary" @click="promptNewFolder">
                  New folder
                </a17-button>
              </div>

              <!-- Type tabs -->
              <ul class="secondarynav secondarynav--desktop" v-if="types.length">
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
                  <a17-button size="small" variant="secondary" @click="promptNewFolder">
                    New folder
                  </a17-button>
                </li>
              </ul>

              <!-- Mobile types dropdown + "New folder" -->
              <div class="secondarynav secondarynav--mobile secondarynav--dropdown">
                <a17-dropdown ref="secondaryNavDropdown" position="bottom-left" width="full" :offset="0">
                  <a17-button
                    class="secondarynav__button"
                    variant="dropdown-transparent"
                    size="small"
                    @click="$refs.secondaryNavDropdown.toggle()"
                    v-if="selectedType"
                  >
                    <span class="secondarynav__link">{{ selectedType.text }}</span>
                    <span class="secondarynav__number">{{ selectedType.total }}</span>
                  </a17-button>
                  <div slot="dropdown__content">
                    <ul>
                      <li v-for="navType in types" :key="navType.value" class="secondarynav__item">
                        <a href="#" @click.prevent="updateType(navType.value)">
                          <span class="secondarynav__link">{{ navType.text }}</span>
                          <span class="secondarynav__number">{{ navType.total }}</span>
                        </a>
                      </li>
                    </ul>
                  </div>
                </a17-dropdown>

                <div class="mt-2">
                  <a17-button size="small" variant="secondary" @click="promptNewFolder">
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
                :placeholder="$trans('media-library.filter-select-label', 'Filter by tag')"
                :searchable="true"
                maxHeight="175px"
              />
              <a17-checkbox
                class="medialibrary__filter-item"
                ref="unused"
                name="unused"
                :initial-value="0"
                :value="1"
                :label="$trans('media-library.unused-filter-label', 'Show unused only')"
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
                @select="selectFolderPath"
                @create="createFolderAtPath"
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
            <footer class="medialibrary__footer" v-if="selectedMedias.length && showInsert && connector">
              <a17-button v-if="canInsert" variant="action" @click="saveAndClose">{{ btnLabel }}</a17-button>
              <a17-button v-else variant="action" :disabled="true">{{ btnLabel }}</a17-button>

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
                <a17-spinner v-if="loading" class="medialibrary__spinner">Loading&hellip;</a17-spinner>
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
      node: { type: Object, required: true }, // { name: '', children: [...] }
      level: { type: Number, default: 0 },
      activePath: { type: Array, default: () => [] } // e.g. ['blogs','campaigns']
    },
    data () {
      return { open: this.level === 0 } // root open by default
    },
    methods: {
      isActiveHere () {
        return this.level < this.activePath.length
          ? this.node.name === this.activePath[this.level]
          : false
      },
      pathHere () {
        const path = []
        let n = this
        while (n && n.node) {
          if (n.level > 0 || n.node.name) path.unshift(n.node.name)
          n = n.$parent
          if (!n || n.$options.name !== 'folder-node') break
        }
        return path
      },
      selectSelf () {
        this.$emit('select', this.pathHere())
      },
      createHere () {
        this.$emit('create', this.pathHere())
      }
    },
    template: `
    <div class="folder-node">
      <div class="folder-node__row" :style="{ paddingLeft: (level * 24) + 'px' }">
        <button class="folder-node__toggle" v-if="node.children && node.children.length"
                @click="open = !open" :aria-expanded="open.toString()">
          <span v-if="open">▾</span><span v-else>▸</span>
        </button>
        <button class="folder-node__name"
                :class="{ 'is-active': isActiveHere() || (level===0 && activePath.length===0) }"
                @click="selectSelf">
          <span v-if="level===0">All</span>
          <span v-else>{{ node.name }}</span>
        </button>
        <button class="folder-node__create" title="New subfolder" @click="createHere">＋</button>
      </div>
      <div v-show="open" class="folder-node__children">
        <folder-node v-for="child in node.children"
                     :key="child.name"
                     :node="child"
                     :level="level+1"
                     :active-path="activePath"
                     @select="$emit('select', $event)"
                     @create="$emit('create', $event)"/>
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
        default: function () {
          return this.$trans('media-library.title', 'Media Library')
        }
      },
      btnLabelSingle: {
        type: String,
        default: function () {
          return this.$trans('media-library.insert', 'Insert')
        }
      },
      btnLabelUpdate: {
        type: String,
        default: function () {
          return this.$trans('media-library.update', 'Update')
        }
      },
      btnLabelMulti: {
        type: String,
        default: function () {
          return this.$trans('media-library.insert', 'Insert')
        }
      },
      initialPage: {
        type: Number,
        default: 1
      },
      authorized: {
        type: Boolean,
        default: false
      },
      showInsert: {
        type: Boolean,
        default: true
      },
      extraMetadatas: {
        type: Array,
        default () {
          return []
        }
      },
      translatableMetadatas: {
        type: Array,
        default () {
          return []
        }
      }
    },
    data: function () {
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
        folderTree: null,           // nested folder tree
        currentFolderPath: []       // e.g. ['folder','sub']
      }
    },
    computed: {
      currentFolderFullPath () {
        return this.currentFolderPath.join('/') // '' at root
      },
      currentFolderLabel () {
        return this.currentFolderPath.length ? this.currentFolderPath[this.currentFolderPath.length - 1] : 'All'
      },
      renderedMediaItems: function () {
        return this.mediaItems.map((item) => {
          item.disabled = (this.filesizeMax > 0 && item.filesizeInMb > this.filesizeMax) ||
            (this.widthMin > 0 && item.width < this.widthMin) ||
            (this.heightMin > 0 && item.height < this.heightMin)
          return item
        })
      },
      currentTypeObject: function () {
        return this.types.find((type) => {
          return type.value === this.type
        })
      },
      endpoint: function () {
        return this.currentTypeObject.endpoint
      },
      modalTitle: function () {
        if (this.connector) {
          if (this.indexToReplace > -1) return this.modalTitlePrefix + ' – ' + this.btnLabelUpdate
          return this.selectedMedias.length > 1 ? this.modalTitlePrefix + ' – ' + this.btnLabelMulti : this.modalTitlePrefix + ' – ' + this.btnLabelSingle
        }
        return this.modalTitlePrefix
      },
      btnLabel: function () {
        let type = this.$trans('media-library.types.single.' + this.type, this.type)

        if (this.indexToReplace > -1) {
          return this.btnLabelUpdate + ' ' + type
        } else {
          if (this.selectedMedias.length > 1) {
            type = this.$trans('media-library.types.multiple.' + this.type, this.type)
          }

          return this.btnLabelSingle + ' ' + type
        }
      },
      usedMedias: function () {
        return this.selected[this.connector] || []
      },
      selectedType: function () {
        const self = this
        const navItem = self.types.filter(function (t) {
          return t.value === self.type
        })
        return navItem[0]
      },
      canInsert: function () {
        return !this.selectedMedias.some(sMedia => !!this.usedMedias.find(uMedia => uMedia.id === sMedia.id))
      },
      ...mapState({
        connector: state => state.mediaLibrary.connector,
        max: state => state.mediaLibrary.max,
        filesizeMax: state => state.mediaLibrary.filesizeMax,
        widthMin: state => state.mediaLibrary.widthMin,
        heightMin: state => state.mediaLibrary.heightMin,
        type: state => state.mediaLibrary.type, // image, video, file
        types: state => state.mediaLibrary.types,
        strict: state => state.mediaLibrary.strict,
        selected: state => state.mediaLibrary.selected,
        indexToReplace: state => state.mediaLibrary.indexToReplace
      })
    },
    watch: {
      type: function () {
        this.clearMediaItems()
        this.gridLoaded = false
        this.loadFolderTree()
      }
    },
    methods: {
      replaceMedia: function ({ id }) {
        this.$refs.uploader.replaceMedia(id)
      },
      open: function () {
        this.$refs.modal.open()
      },
      close: function () {
        this.$refs.modal.hide()
      },
      opened: function () {
        if (!this.gridLoaded) this.reloadGrid()
        if (!this.folderTree) this.loadFolderTree()

        this.listenScrollPosition()

        // empty selected medias (to avoid bugs when adding)
        this.selectedMedias = []

        // in replace mode : select the media to replace when opening
        if (this.connector && this.indexToReplace > -1) {
          const mediaInitSelect = this.selected[this.connector][this.indexToReplace]
          if (mediaInitSelect) {
            this.selectedMedias.push(mediaInitSelect)
          }
        }
      },
      updateType: function (newType) {
        if (this.loading) return
        if (this.strict) return
        if (this.type === newType) return

        this.$store.commit(MEDIA_LIBRARY.UPDATE_MEDIA_TYPE, newType)
        // filter submit will reload grid; folders will be reloaded by watcher
        this.submitFilter()
      },
      addMedia: function (media) {
        const index = this.mediaItems.findIndex(function (item) {
          return item.id === media.id
        })

        // Check if the media item exists i.e replacement
        if (index > -1) {
          for (const mediaRole in this.selected) {
            this.selected[mediaRole].forEach((mediaCrop, index) => {
              if (media.id === mediaCrop.id) {
                const crops = []

                for (const crop in mediaCrop.crops) {
                  crops[crop] = {
                    height: media.height === mediaCrop.height ? mediaCrop.crops[crop].height : media.height,
                    name: crop,
                    width: media.width === mediaCrop.width ? mediaCrop.crops[crop].width : media.width,
                    x: media.width === mediaCrop.width ? mediaCrop.crops[crop].x : 0,
                    y: media.height === mediaCrop.height ? mediaCrop.crops[crop].y : 0
                  }
                }

                this.$store.commit(MEDIA_LIBRARY.UPDATE_MEDIAS, {
                  index,
                  media: {
                    ...media,
                    width: media.width === mediaCrop.width ? mediaCrop.width : media.width,
                    height: media.height === mediaCrop.height ? mediaCrop.height : media.height,
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
          // add media in first position of the available media
          this.mediaItems.unshift(media)
          this.$store.commit(MEDIA_LIBRARY.INCREMENT_MEDIA_TYPE_TOTAL, this.type)
          // select it
          this.updateSelectedMedias(media.id)
        }
      },
      updateSelectedMedias: function (item, shift = false) {
        const id = item.id
        const alreadySelectedMedia = this.selectedMedias.filter(function (media) {
          return media.id === id
        })

        // not already selected
        if (alreadySelectedMedia.length === 0) {
          if (this.max === 1) this.clearSelectedMedias()
          if (this.selectedMedias.length >= this.max && this.max > 0) return

          if (shift && this.selectedMedias.length > 0) {
            const lastSelectedMedia = this.selectedMedias[this.selectedMedias.length - 1]
            const lastSelectedMediaIndex = this.mediaItems.findIndex((media) => media.id === lastSelectedMedia.id)
            const selectedMediaIndex = this.mediaItems.findIndex((media) => media.id === id)
            if (selectedMediaIndex === -1 && lastSelectedMediaIndex === -1) return

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

            selectedMedias.forEach((media) => {
              if (this.selectedMedias.length >= this.max && this.max > 0) return
              const index = this.selectedMedias.findIndex((m) => m.id === media.id)
              if (index === -1) {
                this.selectedMedias.push(media)
              }
            })
          } else {
            const mediaToSelect = this.mediaItems.filter(function (media) {
              return media.id === id
            })

            // Add one media to the selected media
            if (mediaToSelect.length) this.selectedMedias.push(mediaToSelect[0])
          }
        } else {
          // Remove one item from the selected media
          this.selectedMedias = this.selectedMedias.filter(function (media) {
            return media.id !== id
          })
        }
      },
      getFormData: function (form) {
        let data = FormDataAsObj(form)

        if (data) data.page = this.page
        else data = { page: this.page }

        data.type = this.type

        if (Array.isArray(data.unused) && data.unused.length) {
          data.unused = data.unused[0]
        }

        // include current folder
        data.folder = this.currentFolderFullPath || ''

        return data
      },
      clearFilters: function () {
        const self = this
        // reset tags
        if (this.$refs.filter) this.$refs.filter.value = null
        // reset unused field
        if (this.$refs.unused) {
          const input = this.$refs.unused.$el.querySelector('input')
          input && input.checked && input.click()
        }

        this.$nextTick(function () {
          self.submitFilter()
        })
      },

      // ------- FOLDERS -------
      loadFolderTree () {
        // Fetch a nested tree for the current type
        api.getFolders(
          this.endpoint,
          { type: this.type },
          (resp) => {
            // Expect { name: '', children: [ { name: 'folder', children: [...] } ] }
            this.folderTree = resp.data.tree || { name: '', children: [] }
          },
          (error) => {
            this.folderTree = { name: '', children: [] }
            this.$store.commit(NOTIFICATION.SET_NOTIF, {
              message: error.data?.message || 'Unable to load folders',
              variant: 'error'
            })
          }
        )
      },
      selectFolderPath (pathArray) {
        this.currentFolderPath = Array.isArray(pathArray) ? pathArray : []
        this.submitFilter()
      },
      goToRoot () {
        this.selectFolderPath([])
      },
      goToIndex (i) {
        this.selectFolderPath(this.currentFolderPath.slice(0, i + 1))
      },
      promptNewFolder () {
        const name = window.prompt(this.$trans('media-library.new-folder', 'New folder name'))
        if (!name) return
        this.createFolderAtPath(this.currentFolderPath, name)
      },
      createFolderAtPath (parentPath, forcedName = null) {
        const name =
          forcedName ||
          window.prompt(this.$trans('media-library.new-subfolder', 'New subfolder name'))
        if (!name) return
        api.createFolder(
          this.endpoint,
          {
            type: this.type,
            parent: (parentPath || []).join('/'),
            name
          },
          () => {
            // stay in the same folder and reload the list
            this.submitFilter()
            // refresh tree
            this.loadFolderTree()
          },
          (error) => {
            this.$store.commit(NOTIFICATION.SET_NOTIF, {
              message: error.data?.message || 'Unable to create folder',
              variant: 'error'
            })
          }
        )
      },
      moveSelectedToCurrentFolder () {
        if (!this.selectedMedias.length) return
        api.moveToFolder(
          this.endpoint,
          {
            type: this.type,
            target: this.currentFolderFullPath, // '' for root
            mediaIds: this.selectedMedias.map(m => m.id)
          },
          () => {
            this.$store.commit(NOTIFICATION.SET_NOTIF, {
              message: this.$trans('media-library.moved', 'Moved to folder'),
              variant: 'success'
            })
            // reload items for current folder
            this.page = 1
            this.clearMediaItems()
            this.reloadGrid()
          },
          (error) => {
            this.$store.commit(NOTIFICATION.SET_NOTIF, {
              message: error.data?.message || 'Unable to move items',
              variant: 'error'
            })
          }
        )
      },
      // ------- /FOLDERS -------

      clearSelectedMedias: function () {
        this.selectedMedias.splice(0)
      },
      deleteSelectedMedias: function (mediasIds) {
        let keepSelectedMedias = []
        if (mediasIds && mediasIds.length !== this.selectedMedias.length) {
          keepSelectedMedias = this.selectedMedias.filter((media) => !media.deleteUrl)
        }
        mediasIds.forEach(() => {
          this.$store.commit(MEDIA_LIBRARY.DECREMENT_MEDIA_TYPE_TOTAL, this.type)
        })
        this.mediaItems = this.mediaItems.filter((media) => {
          return !this.selectedMedias.includes(media) || keepSelectedMedias.includes(media)
        })
        this.selectedMedias = keepSelectedMedias
        if (this.mediaItems.length <= 40) {
          this.reloadGrid()
        }
      },
      clearMediaItems: function () {
        this.mediaItems.splice(0)
      },
      reloadGrid: function () {
        this.loading = true

        const form = this.$refs.form
        const formdata = this.getFormData(form)

        // see api/media-library for actual ajax
        api.get(
          this.endpoint,
          formdata,
          (resp) => {
            // add medias here
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
          (error) => {
            this.$store.commit(NOTIFICATION.SET_NOTIF, {
              message: error.data.message,
              variant: 'error'
            })
          }
        )
      },
      reloadTags: function (tags = []) {
        this.tags = tags
      },
      submitFilter: function () {
        const self = this
        const el = this.$refs.list
        // when changing filters, reset the page to 1
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
          onComplete: function () {
            self.reloadGrid()
          }
        })
      },
      listenScrollPosition: function () {
        // re-listen for scroll position
        this.$nextTick(function () {
          if (!this.gridLoaded) return

          const list = this.$refs.list
          if (this.gridHeight !== list.scrollHeight) {
            list.addEventListener('scroll', this.scrollToPaginate)
          }
        })
      },
      scrollToPaginate: function () {
        if (!this.gridLoaded) return

        const list = this.$refs.list
        const offset = 10

        if (list.scrollTop > this.lastScrollTop && list.scrollTop + list.offsetHeight > list.scrollHeight - offset) {
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
      saveAndClose: function () {
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
  $width_sidebar: (default: 290px, small: 250px, xsmall: 200px);

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

    @include breakpoint(small-) {
      .secondarynav {
        padding-bottom: 10px;
      }
    }
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

  /* LEFT: folder tree */
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
      display: none; /* hide on small screens */
    }
  }

  /* RIGHT: footer under right sidebar width */
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

  /* RIGHT: details panel */
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

  /* CENTER: list fits between left tree and right sidebar */
  .medialibrary__list {
    margin: 0;
    position: absolute;
    top: 0;
    left: 220px; /* leave room for folder tree */
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
      left: 0; /* when tree is hidden */
    }

    @media screen and (max-width: 550px) {
      right: 0; /* when right sidebar hidden */
    }
  }

  .medialibrary__list-items {
    position: relative;
    display: block;
    width: 100%;
    min-height: 100%;
  }

  /* simple folder tree styles */
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

  /* header helpers */
  .medialibrary__folders-nav {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
  }
  .breadcrumbs a { text-decoration: none; }
  .breadcrumbs .sep { margin: 0 6px; color: #999; }
  .breadcrumbs .is-active { font-weight: 600; }
  .ml-2 { margin-left: 8px; }
  .mt-2 { margin-top: 8px; }
</style>
