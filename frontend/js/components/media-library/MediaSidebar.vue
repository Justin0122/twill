<template>
  <div class="mediasidebar">
    <a17-mediasidebar-upload v-if="mediasLoading.length"/>

    <template v-else>
      <div v-if="folderError" class="mediasidebar__foldererror">
        <div class="mediasidebar__foldererror-head">
          <strong>{{ folderError }}</strong>
          <button
            class="foldererror__close"
            type="button"
            @click="$emit('clearFolderError')"
          >
            ×
          </button>
        </div>

        <ul class="foldererror__items">
          <li
            v-for="item in folderErrorUsed"
            :key="item.media_id"
            class="foldererror__item"
          >
            <div class="foldererror__file">
              <strong>{{ item.filename || 'Media #' + item.media_id }}</strong>
            </div>
            <ul class="foldererror__places">
              <li v-for="(p, i) in item.places" :key="item.media_id + '-' + i">
                <code>{{ p.type }}</code> #{{ p.id }}
                <span v-if="p.admin_url">
                  <a :href="p.admin_url" target="_blank">{{ p.admin_url }}</a>
                </span>
                <span v-if="p.title"> — {{ p.title }}</span>
              </li>
            </ul>
          </li>
        </ul>
      </div>
      <div class="mediasidebar__inner" :class="containerClasses">
        <p v-if="!hasMedia" class="f--note">
          {{ $trans('media-library.sidebar.empty-text', 'No file selected') }}
        </p>
        <p v-if="hasMultipleMedias" class="mediasidebar__info">
          {{ medias.length }}
          {{
            $trans('media-library.sidebar.files-selected', 'files selected')
          }}
          <a href="#" @click.prevent="clear">{{
              $trans('media-library.sidebar.clear', 'Clear')
            }}</a>
        </p>

        <template v-if="hasSingleMedia">
          <img
            v-if="isImage"
            :src="firstMedia.thumbnail"
            class="mediasidebar__img"
            :alt="firstMedia.original"
          />
          <p class="mediasidebar__name">{{ firstMedia.name }}</p>
          <ul class="mediasidebar__metadatas">
            <li class="f--small" v-if="firstMedia.size">
              File size: {{ uppercase(firstMedia.size) }}
            </li>
            <li
              class="f--small"
              v-if="isImage && firstMedia.width + firstMedia.height"
            >
              {{ $trans('media-library.sidebar.dimensions', 'Dimensions') }}:
              {{ firstMedia.width }} &times; {{ firstMedia.height }}
            </li>
          </ul>
        </template>
        <template v-if="shouldShowRefs">
          <p class="mediasidebar__reference_label">
            {{ $trans('media-library.sidebar.references', 'References') }}
          </p>

          <!-- Single media (grouped by module) -->
          <ul v-if="hasSingleMedia" class="mediasidebar__metadatas usage">
            <li v-if="!ownersCount(firstMedia)" class="f--tiny f--note">
              {{ $trans('media-library.sidebar.no-references', 'No references') }}
            </li>

            <li v-for="mod in moduleKeysSingle"
                :key="`mod_single_${mod}`"
                class="owners-module">
              <button type="button"
                      class="module-toggle"
                      @click="toggleModuleSingle(mod)">
                <span class="chev">{{ isModuleOpenSingle(mod) ? '▼' : '►' }}</span>
                <span class="module-badge">{{ mod }}</span>
                <span class="count">({{ groupedOwnersSingle[mod].length }})</span>
              </button>

              <ul v-show="isModuleOpenSingle(mod)"
                  class="ownerslist"
                  :class="{'owners--scroll': groupedOwnersSingle[mod].length > 8}">
                <li class="f--small"
                    v-for="(item, index) in groupedOwnersSingle[mod]"
                    :key="`mediaowner_${firstMedia.id}_${mod}_${item.id ?? index}`">
        <span class="ownerline">
          <a v-if="item.edit || item.admin_url"
             :href="item.edit || item.admin_url"
             target="_blank">
            {{ item.title || item.name || ((item.type || 'Item') + ' #' + (item.id ?? '?')) }}
          </a>
          <span v-else>
            {{ item.title || item.name || ((item.type || 'Item') + ' #' + (item.id ?? '?')) }}
          </span>
        </span>
                </li>
              </ul>
            </li>
          </ul>

          <!-- Multiple medias (grouped by module per media) -->
          <ul v-else class="mediasidebar__metadatas usage">
            <li class="f--small" v-for="m in medias" :key="`owners_of_${m.id}`">
              <strong>{{ m.name || ('Media #' + m.id) }}</strong>

              <ul class="mediasidebar__metadatas usage">
                <li v-if="!ownersCount(m)" class="f--tiny f--note">
                  {{ $trans('media-library.sidebar.no-references', 'No references') }}
                </li>

                <li v-for="mod in moduleKeysFor(m)"
                    :key="`mod_${m.id}_${mod}`"
                    class="owners-module">
                  <button type="button"
                          class="module-toggle"
                          @click="toggleModuleFor(m, mod)">
                    <span class="chev">{{ isModuleOpenFor(m, mod) ? '▼' : '►' }}</span>
                    <span class="module-badge">{{ mod }}</span>
                    <span class="count">({{ groupedOwnersFor(m)[mod].length }})</span>
                  </button>

                  <ul v-show="isModuleOpenFor(m, mod)"
                      class="ownerslist"
                      :class="{'owners--scroll': groupedOwnersFor(m)[mod].length > 8}">
                    <li class="f--small"
                        v-for="(item, index) in groupedOwnersFor(m)[mod]"
                        :key="`mediaowner_${m.id}_${mod}_${item.id ?? index}`">
            <span class="ownerline">
              <a v-if="item.edit || item.admin_url"
                 :href="item.edit || item.admin_url"
                 target="_blank">
                {{ item.title || item.name || ((item.type || 'Item') + ' #' + (item.id ?? '?')) }}
              </a>
              <span v-else>
                {{ item.title || item.name || ((item.type || 'Item') + ' #' + (item.id ?? '?')) }}
              </span>
            </span>
                    </li>
                  </ul>
                </li>
              </ul>
            </li>
          </ul>

        </template>
        <a17-buttonbar class="mediasidebar__buttonbar" v-if="hasMedia">
          <!-- Actions -->
          <a v-if="hasSingleMedia" :href="firstMedia.original" download
          ><span v-svg symbol="download"></span
          ></a>
          <button
            v-if="allowDelete && authorized"
            type="button"
            @click="deleteSelectedMediasValidation"
          >
            <span v-svg symbol="trash"></span>
          </button>
          <button
            v-else
            type="button"
            class="button--disabled"
            :data-tooltip-title="warningDeleteMessage"
            v-tooltip
          >
            <span v-svg symbol="trash"></span>
          </button>
          <button v-if="hasSingleMedia" type="button" @click="replaceMedia">
            <span v-svg symbol="replace"></span>
          </button>
        </a17-buttonbar>
      </div>

      <form
        v-if="hasMedia"
        ref="form"
        class="mediasidebar__inner mediasidebar__form"
        @submit="submit"
      >
        <span class="mediasidebar__loader" v-if="loading"
        ><span class="loader loader--small"><span></span></span
        ></span>
        <a17-vselect
          v-if="!fieldsRemovedFromBulkEditing.includes('tags')"
          :label="$trans('media-library.sidebar.tags')"
          :key="firstMedia.id + '-' + medias.length"
          name="tags"
          :multiple="true"
          :selected="hasMultipleMedias ? sharedTags : firstMedia.tags"
          :searchable="true"
          :emptyText="
            $trans('media-library.no-tags-found', 'Sorry, no tags found.')
          "
          :taggable="true"
          :pushTags="true"
          size="small"
          :endpoint="type.tagsEndpoint"
          @change="save"
          maxHeight="175px"
        />
        <span
          v-if="
            extraMetadatas.length &&
              isImage &&
              hasMultipleMedias &&
              !fieldsRemovedFromBulkEditing.includes('tags')
          "
          class="f--tiny f--note f--underlined"
          @click="removeFieldFromBulkEditing('tags')"
          data-tooltip-title="Remove this field if you do not want to update it on all selected medias"
          data-tooltip-theme="default"
          data-tooltip-placement="top"
          v-tooltip
        >Remove from bulk edit</span
        >
        <template v-if="hasMultipleMedias">
          <input type="hidden" name="ids" :value="mediasIds"/>
        </template>
        <template v-else>
          <input type="hidden" name="id" :value="firstMedia.id"/>
          <div
            class="mediasidebar__langswitcher"
            v-if="translatableMetadatas.length > 0"
          >
            <a17-langswitcher :in-modal="true" :all-published="true"/>
          </div>

          <a17-locale
            type="a17-textfield"
            v-if="isImage && translatableMetadatas.includes('alt_text')"
            :attributes="{
              label: $trans('media-library.sidebar.alt-text', 'Alt text'),
              name: 'alt_text',
              type: 'text',
              size: 'small'
            }"
            :keepInDom="true"
            :initialValues="altValues"
            @focus="focus"
            @blur="blur"
          ></a17-locale>
          <a17-textfield
            v-else-if="isImage"
            :label="$trans('media-library.sidebar.alt-text', 'Alt text')"
            name="alt_text"
            :initialValue="firstDefaultMeta.altText || ''"
            size="small"
            @focus="focus"
            @blur="blur"
          />

          <template v-if="useWysiwyg">
            <a17-locale
              type="a17-wysiwyg"
              v-if="isImage && translatableMetadatas.includes('caption')"
              :attributes="{
                options: wysiwygOptions,
                label: $trans('media-library.sidebar.caption', 'Caption'),
                name: 'caption',
                size: 'small'
              }"
              :keepInDom="true"
              :initialValues="captionValues"
              @focus="focus"
              @blur="blur"
            ></a17-locale>
            <a17-wysiwyg
              v-else-if="isImage"
              type="textarea"
              :rows="1"
              size="small"
              :label="$trans('media-library.sidebar.caption', 'Caption')"
              name="caption"
              :options="wysiwygOptions"
              :initialValue="firstDefaultMeta.caption || ''"
              @focus="focus"
              @blur="blur"
            />
          </template>
          <template v-else>
            <a17-locale
              type="a17-textfield"
              v-if="isImage && translatableMetadatas.includes('caption')"
              :attributes="{
                type: 'textarea',
                rows: 1,
                label: $trans('media-library.sidebar.caption', 'Caption'),
                name: 'caption',
                size: 'small'
              }"
              :keepInDom="true"
              :initialValues="captionValues"
              @focus="focus"
              @blur="blur"
            ></a17-locale>
            <a17-textfield
              v-else-if="isImage"
              type="textarea"
              :rows="1"
              size="small"
              :label="$trans('media-library.sidebar.caption', 'Caption')"
              name="caption"
              :initialValue="firstDefaultMeta.caption || ''"
              @focus="focus"
              @blur="blur"
            />
          </template>

          <template v-for="field in singleOnlyMetadatas">
            <a17-locale
              type="a17-textfield"
              v-bind:key="field.name"
              v-if="
                isImage &&
                  (field.type === 'text' || !field.type) &&
                  translatableMetadatas.includes(field.name)
              "
              :keepInDom="true"
              :attributes="{
                label: field.label,
                name: field.name,
                type: 'textarea',
                rows: 1,
                size: 'small'
              }"
              :initialValues="(firstDefaultMeta[field.name] && typeof firstDefaultMeta[field.name] === 'object') ? firstDefaultMeta[field.name] : {}"
              @focus="focus"
              @blur="blur"
            />
            <a17-textfield
              v-bind:key="field.name"
              v-else-if="isImage && (field.type === 'text' || !field.type)"
              :label="field.label"
              :name="field.name"
              size="small"
              :initialValue="firstDefaultMeta[field.name] ?? ''"
              type="textarea"
              :rows="1"
              @focus="focus"
              @blur="blur"
            />
            <div
              class="mediasidebar__checkbox"
              v-if="isImage && field.type === 'checkbox'"
              v-bind:key="field.name"
            >
              <a17-checkbox
                :label="field.label"
                :name="field.name"
                :initialValue="firstDefaultMeta[field.name] ?? false"
                :value="1"
                @change="blur"
              />
            </div>
          </template>
        </template>
        <template v-for="field in singleAndMultipleMetadatas">
          <a17-locale
            type="a17-textfield"
            v-bind:key="field.name"
            v-if="
              isImage &&
                (field.type === 'text' || !field.type) &&
                ((hasMultipleMedias &&
                  !fieldsRemovedFromBulkEditing.includes(field.name)) ||
                  hasSingleMedia) &&
                translatableMetadatas.includes(field.name)
            "
            :keepInDom="true"
            :attributes="{
              label: field.label,
              name: field.name,
              type: 'textarea',
              rows: 1,
              size: 'small'
            }"
            :initialValues="sharedMetadata(field.name, 'object')"
            @focus="focus"
            @blur="blur"
          />
          <a17-textfield
            v-bind:key="field.name"
            v-else-if="
              isImage &&
                (field.type === 'text' || !field.type) &&
                ((hasMultipleMedias &&
                  !fieldsRemovedFromBulkEditing.includes(field.name)) ||
                  hasSingleMedia)
            "
            :label="field.label"
            :name="field.name"
            size="small"
            :initialValue="sharedMetadata(field.name)"
            type="textarea"
            :rows="1"
            @focus="focus"
            @blur="blur"
          />
          <div
            class="mediasidebar__checkbox"
            v-bind:key="field.name"
            v-if="
              isImage &&
                field.type === 'checkbox' &&
                ((hasMultipleMedias &&
                  !fieldsRemovedFromBulkEditing.includes(field.name)) ||
                  hasSingleMedia)
            "
          >
            <a17-checkbox
              v-bind:key="field.name"
              :label="field.label"
              :name="field.name"
              :initialValue="sharedMetadata(field.name, 'boolean')"
              :value="1"
              @change="blur"
            />
          </div>
          <span
            class="f--tiny f--note f--underlined"
            @click="removeFieldFromBulkEditing(field.name)"
            v-if="
              isImage &&
                hasMultipleMedias &&
                !fieldsRemovedFromBulkEditing.includes(field.name)
            "
            v-bind:key="field.name"
            data-tooltip-title="Remove this field if you do not want to update it on all selected medias"
            data-tooltip-theme="default"
            data-tooltip-placement="top"
            v-tooltip
          >Remove from bulk edit</span
          >
        </template>
      </form>
    </template>

    <a17-modal
      class="modal--tiny modal--form modal--withintro"
      ref="warningDelete"
      title="Warning Delete"
    >
      <p class="modal--tiny-title">
        <strong>{{
            $trans('media-library.dialogs.delete.title', 'Are you sure ?')
          }}</strong>
      </p>
      <p>{{ warningDeleteMessage }}</p>
      <a17-inputframe>
        <a17-button variant="validate" @click="deleteSelectedMedias"
        >Delete ({{ mediasIdsToDelete.length }})
        </a17-button>
        <a17-button variant="aslink" @click="$refs.warningDelete.close()"
        ><span>Cancel</span></a17-button
        >
      </a17-inputframe>
    </a17-modal>
  </div>
</template>

<script>
  import isEqual from 'lodash/isEqual'
  import {mapState} from 'vuex'

  import a17Langswitcher from '@/components/LangSwitcher'
  import a17MediaSidebarUpload from '@/components/media-library/MediaSidebarUpload'
  import api from '@/store/api/media-library'
  import {NOTIFICATION} from '@/store/mutations'
  import {uppercase} from '@/utils/filters.js'
  import FormDataAsObj from '@/utils/formDataAsObj.js'

  export default {
    name: 'A17MediaSidebar',
    emits: ['triggerMediaReplace', 'delete', 'clear', 'tagUpdated'],
    components: {
      'a17-mediasidebar-upload': a17MediaSidebarUpload,
      'a17-langswitcher': a17Langswitcher
    },
    props: {
      medias: {
        default: function () {
          return []
        }
      },
      authorized: {
        type: Boolean,
        default: false
      },
      type: {
        type: Object,
        required: true
      },
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
        focused: false,
        previousSavedData: {},
        fieldsRemovedFromBulkEditing: [],
        ownersExpandedSingleModules: Object.create(null),
        ownersExpandedModulesMap: Object.create(null), // mediaId -> { [module]: bool }
      }
    },
    watch: {
      medias: function () {
        this.fieldsRemovedFromBulkEditing = []
        this.ownersExpandedSingleModules = Object.create(null)
        this.ownersExpandedModulesMap = Object.create(null)
      }
    },
    computed: {
      totalOwnersAll() {
        return (this.medias || []).reduce((n, m) => n + this.ownersOf(m).length, 0);
      },
      shouldShowRefs() {
        return this.hasMedia && this.showMediaReferences && this.totalOwnersAll > 0;
      },
      groupedOwnersSingle() {
        return this.groupByModule(this.safeOwners)
      },

      // Sorted module keys for the single-media view
      moduleKeysSingle() {
        return Object.keys(this.groupedOwnersSingle).sort()
      },
      totalOwnersSingle() {
        return this.ownersCount(this.firstMedia);
      },
      showMoreSingle() {
        return this.totalOwnersSingle > 5;
      },
      visibleOwnersSingle() {
        const list = this.safeOwners;
        return this.ownersExpandedSingle ? list : list.slice(0, 5);
      },
      ownersMap() {
        const map = new Map();
        (this.medias || []).forEach(m => {
          const list =
            (Array.isArray(m?.owners) && m.owners) ||
            (Array.isArray(m?.metadatas?.default?.owners) && m.metadatas.default.owners) ||
            (Array.isArray(m?.metadatas?.custom?.owners) && m.metadatas.custom.owners) ||
            [];
          map.set(m?.id, Array.isArray(list) ? list : []);
        });
        return map;
      },
      safeOwners() {
        return this.firstMedia ? (this.ownersMap.get(this.firstMedia.id) || []) : [];
      },
      firstDefaultMeta() {
        const fm = this.firstMedia
        return (fm && fm.metadatas && fm.metadatas.default) ? fm.metadatas.default : {}
      },
      firstMedia: function () {
        return this.hasMedia ? this.medias[0] : null
      },
      hasMultipleMedias: function () {
        return this.medias.length > 1
      },
      hasSingleMedia: function () {
        return this.medias.length === 1
      },
      hasMedia: function () {
        return this.medias.length > 0
      },
      isImage: function () {
        return this.type.value === 'image'
      },
      sharedTags() {
        if (!this.medias.length) return [];
        return this.medias
          .map(m => Array.isArray(m?.tags) ? m.tags : [])
          .reduce((acc, tags) => acc.filter(t => tags.includes(t)));
      },
      captionValues() {
        const v = this.firstDefaultMeta.caption
        return v && typeof v === 'object' ? v : {}
      },
      altValues() {
        const v = this.firstDefaultMeta.altText
        return v && typeof v === 'object' ? v : {}
      },
      sharedMetadata() {
        return (name, type) => {
          // Single selection: just read from safe meta
          if (!this.hasMultipleMedias) {
            const v = this.firstDefaultMeta[name]
            if (type === 'boolean') return !!v
            if (type === 'object') return (v && typeof v === 'object') ? v : {}
            // default (string-ish)
            return v ?? ''
          }

          // Multiple selection: compare across medias (with guards)
          const vals = this.medias.map(m => {
            const meta = (m && m.metadatas && m.metadatas.default) ? m.metadatas.default : {}
            return meta[name]
          })

          const allEqual = vals.every((val, i, arr) => {
            const a0 = arr[0]
            return Array.isArray(val) ? (val?.[0] === a0?.[0]) : (val === a0)
          })

          if (allEqual) {
            const v = this.firstDefaultMeta[name]
            if (type === 'boolean') return !!v
            if (type === 'object') return (v && typeof v === 'object') ? v : {}
            return v ?? ''
          }

          // Not shared—return neutral value by expected type
          if (type === 'object') return {}
          if (type === 'boolean') return false
          return ''
        }
      },
      mediasIds: function () {
        return this.medias
          .map(function (media) {
            return media.id
          })
          .join(',')
      },
      mediasIdsToDelete: function () {
        return this.medias
          .filter(media => media.deleteUrl)
          .map(media => media.id)
      },
      mediasIdsToDeleteString: function () {
        return this.mediasIdsToDelete.join(',')
      },
      allowDelete: function () {
        return (
          this.medias.every(media => {
            return media.deleteUrl
          }) ||
          (this.hasMultipleMedias &&
            !this.medias.every(media => {
              return !media.deleteUrl
            }))
        )
      },
      warningDeleteMessage: function () {
        if (this.allowDelete) {
          if (this.hasMultipleMedias) {
            return this.$trans(
              'media-library.dialogs.delete.allow-delete-multiple-medias',
              "Some files are used and can't be deleted. Do you want to delete the others ?"
            )
          } else {
            return this.$trans(
              'media-library.dialogs.delete.allow-delete-one-media',
              "This file is used and can't be deleted. Do you want to delete the others ?"
            )
          }
        } else {
          if (this.hasMultipleMedias) {
            return this.$trans(
              'media-library.dialogs.delete.dont-allow-delete-multiple-medias',
              "This files are used and can't be deleted."
            )
          } else {
            return this.$trans(
              'media-library.dialogs.delete.dont-allow-delete-one-media',
              "This file is used and can't be deleted."
            )
          }
        }
      },
      containerClasses: function () {
        return {
          'mediasidebar__inner--multi': this.hasMultipleMedias,
          'mediasidebar__inner--single': this.hasSingleMedia
        }
      },
      singleAndMultipleMetadatas: function () {
        return this.extraMetadatas.filter(
          m => m.multiple && !this.translatableMetadatas.includes(m.name)
        )
      },
      singleOnlyMetadatas: function () {
        return this.extraMetadatas.filter(
          m =>
            !m.multiple ||
            (m.multiple && this.translatableMetadatas.includes(m.name))
        )
      },
      ...mapState({
        useWysiwyg: state => state.mediaLibrary.config.useWysiwyg,
        wysiwygOptions: state => state.mediaLibrary.config.wysiwygOptions,
        mediasLoading: state => state.mediaLibrary.loading,

        showMediaReferences: state => {
          const cfg = state.mediaLibrary && state.mediaLibrary.config
          return (cfg && typeof cfg.showMediaReferences === 'boolean')
            ? cfg.showMediaReferences
            : true
        }
      })
    },
    methods: {
      uppercase,
      replaceMedia: function () {
        // Open confirm dialog if any
        if (this.$root.$refs.replaceWarningMediaLibrary) {
          this.$root.$refs.replaceWarningMediaLibrary.open(() => {
            this.triggerMediaReplace()
          })
        } else {
          this.triggerMediaReplace()
        }
      },
      groupByModule(list) {
        return (list || []).reduce((acc, item) => {
          const mod = item?.module || item?.type || 'other'
          if (!acc[mod]) acc[mod] = []
          acc[mod].push(item)
          return acc
        }, {})
      },
      // --- Single selection expand/collapse ---
      isModuleOpenSingle(mod) {
        // default CLOSED: change `|| true` to open by default
        return !!this.ownersExpandedSingleModules[mod]
      },
      toggleModuleSingle(mod) {
        const current = !!this.ownersExpandedSingleModules[mod]
        if (this.$set) this.$set(this.ownersExpandedSingleModules, mod, !current)
        else this.ownersExpandedSingleModules[mod] = !current
      },

// --- Multi selection expand/collapse ---
      groupedOwnersFor(m) {
        return this.groupByModule(this.ownersOf(m))
      },
      moduleKeysFor(m) {
        return Object.keys(this.groupedOwnersFor(m)).sort()
      },
      isModuleOpenFor(m, mod) {
        const id = m?.id
        if (!id) return false
        const bucket = this.ownersExpandedModulesMap[id] || {}
        return !!bucket[mod]
      },
      toggleModuleFor(m, mod) {
        const id = m?.id
        if (!id) return
        if (!this.ownersExpandedModulesMap[id]) {
          if (this.$set) this.$set(this.ownersExpandedModulesMap, id, {})
          else this.ownersExpandedModulesMap[id] = {}
        }
        const current = !!this.ownersExpandedModulesMap[id][mod]
        if (this.$set) this.$set(this.ownersExpandedModulesMap[id], mod, !current)
        else this.ownersExpandedModulesMap[id][mod] = !current
      },
      triggerMediaReplace: function () {
        this.$emit('triggerMediaReplace', {
          id: this.getMediaToReplaceId()
        })
      },
      deleteSelectedMediasValidation: function () {
        if (this.loading) return false

        if (this.mediasIdsToDelete.length !== this.medias.length) {
          this.$refs.warningDelete.open()
          return
        }

        // Open confirm dialog if any
        if (this.$root.$refs.deleteWarningMediaLibrary) {
          this.$root.$refs.deleteWarningMediaLibrary.open(() => {
            this.deleteSelectedMedias()
          })
        } else {
          this.deleteSelectedMedias()
        }
      },
      isOwnersExpanded(m) {
        return !!this.ownersExpandedMap[m?.id];
      },
      toggleOwnersExpanded(m) {
        const id = m?.id;
        if (!id) return;
        this.$set ? this.$set(this.ownersExpandedMap, id, !this.ownersExpandedMap[id])
          : (this.ownersExpandedMap[id] = !this.ownersExpandedMap[id]);
      },
      totalOwnersFor(m) {
        return this.ownersOf(m).length;
      },
      showMoreFor(m) {
        return this.totalOwnersFor(m) > 5;
      },
      visibleOwnersFor(m) {
        const owners = this.ownersOf(m);
        return this.isOwnersExpanded(m) ? owners : owners.slice(0, 5);
      },
      deleteSelectedMedias: function () {
        if (this.loading) return false
        this.loading = true

        if (this.hasMultipleMedias) {
          api.bulkDelete(
            this.firstMedia.deleteBulkUrl,
            {ids: this.mediasIdsToDeleteString},
            resp => {
              this.loading = false
              this.$emit('delete', this.mediasIdsToDelete)
              this.$refs.warningDelete.close()
            },
            error => {
              this.$store.commit(NOTIFICATION.SET_NOTIF, {
                message: error.data.message,
                variant: 'error'
              })
            }
          )
        } else {
          api.delete(
            this.firstMedia.deleteUrl,
            resp => {
              this.loading = false
              this.$emit('delete', this.mediasIdsToDelete)
              this.$refs.warningDelete.close()
            },
            error => {
              this.$store.commit(NOTIFICATION.SET_NOTIF, {
                message: error.data.message,
                variant: 'error'
              })
            }
          )
        }
      },
      ownersOf(m) {
        if (!m) return [];
        const owners =
          (Array.isArray(m.owners) && m.owners) ||
          (Array.isArray(m?.metadatas?.default?.owners) && m.metadatas.default.owners) ||
          (Array.isArray(m?.metadatas?.custom?.owners) && m.metadatas.custom.owners) ||
          [];
        return Array.isArray(owners) ? owners : [];
      },
      // tiny helper if you want to avoid `.length` in templates
      ownersCount(m) {
        return this.ownersOf(m).length;
      },
      ensureDefaultMeta(media) {
        if (!media.metadatas) media.metadatas = {}
        if (!media.metadatas.default) media.metadatas.default = {}
        return media.metadatas.default
      },
      clear: function () {
        this.$emit('clear')
      },
      getFormData: function (form) {
        return FormDataAsObj(form)
      },
      getMediaToReplaceId: function () {
        return this.firstMedia.id
      },
      removeFieldFromBulkEditing: function (name) {
        this.fieldsRemovedFromBulkEditing.push(name)
      },
      focus: function () {
        this.focused = true
      },
      blur: function () {
        this.focused = false
        this.save()

        const form = this.$refs.form
        const data = this.getFormData(form)

        if (this.hasSingleMedia) {
          const meta = this.ensureDefaultMeta(this.firstMedia)

          if (Object.prototype.hasOwnProperty.call(data, 'alt_text')) {
            meta.altText = data.alt_text
          } else {
            meta.altText = ''
          }

          if (Object.prototype.hasOwnProperty.call(data, 'caption')) {
            meta.caption = data.caption
          } else {
            meta.caption = ''
          }

          this.extraMetadatas.forEach(metadata => {
            if (Object.prototype.hasOwnProperty.call(data, metadata.name)) {
              meta[metadata.name] = data[metadata.name]
            } else {
              meta[metadata.name] = ''
            }
          })
        } else {
          this.singleAndMultipleMetadatas.forEach(metadata => {
            if (Object.prototype.hasOwnProperty.call(data, metadata.name)) {
              this.medias.forEach(media => {
                const meta = this.ensureDefaultMeta(media)
                meta[metadata.name] = data[metadata.name]
              })
            }
          })
        }
      },
      save: function () {
        this.$nextTick(() => {
          const form = this.$refs.form
          if (!form) return

          const formData = this.getFormData(form)

          if (!isEqual(formData, this.previousSavedData) && !this.loading) {
            this.previousSavedData = formData
            this.update(form)
          }
        })
      },
      submit: function (event) {
        event.preventDefault()
        this.save()
      },
      update: function (form) {
        if (this.loading) return

        this.loading = true

        const data = this.getFormData(form)
        data.fieldsRemovedFromBulkEditing = this.fieldsRemovedFromBulkEditing

        const url = this.hasMultipleMedias
          ? this.firstMedia.updateBulkUrl
          : this.firstMedia.updateUrl // single or multi updates

        api.update(
          url,
          data,
          resp => {
            this.loading = false

            // Refresh the select filter displaying all tags
            if (resp.data.tags) this.$emit('tagUpdated', resp.data.tags)

            // Bulk update : Refresh tags
            if (this.hasMultipleMedias && resp.data.items) {
              // Update the tags of all the selected medias
              this.medias.forEach(function (media) {
                resp.data.items.some(function (mediaFromResp) {
                  if (mediaFromResp.id === media.id)
                    media.tags = mediaFromResp.tags // replace tags with the one from the response
                  return mediaFromResp.id === media.id
                })
              })
            }
          },
          error => {
            this.loading = false

            if (error.data.message) {
              this.$store.commit(NOTIFICATION.SET_NOTIF, {
                message: error.data.message,
                variant: 'error'
              })
            }
          }
        )
      }
    }
  }
</script>

<style lang="scss" scoped>
  .mediasidebar {
    a {
      color: $color__link;
      text-decoration: none;

      &:focus,
      &:hover {
        text-decoration: underline;
      }
    }
  }

  .mediasidebar__info {
    margin-bottom: 30px;

    a {
      margin-left: 15px;
    }
  }

  .mediasidebar__inner {
    padding: 20px;
    // overflow: hidden;
  }

  .mediasidebar__img {
    max-width: 135px;
    max-height: 135px;
    height: auto;
    display: block;
    margin-bottom: 17px;
  }

  .mediasidebar__name {
    margin-bottom: 6px;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .mediasidebar__metadatas {
    color: $color__text--light;
    margin-bottom: 16px;
  }

  .mediasidebar .mediasidebar__buttonbar {
    display: inline-block;
  }

  .mediasidebar__form {
    border-top: 1px solid $color__border;
    position: relative;

    button {
      margin-top: 16px;
    }

    &.mediasidebar__form--loading {
      opacity: 0.5;
    }
  }

  .mediasidebar__loader {
    position: absolute;
    top: 20px;
    right: 20px + 8px + 8px;
  }

  .mediasidebar__checkbox {
    margin-top: 16px;
  }

  .mediasidebar__langswitcher {
    margin-top: 32px;
    margin-bottom: 32px;
  }

  .mediasidebar__foldererror {
    margin: 12px 20px 0;
    padding: 12px;
    border: 1px solid #f3c2c2;
    background: #fff6f6;
    border-radius: 8px;

    .mediasidebar__foldererror-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 8px;
    }

    .foldererror__close {
      background: transparent;
      border: 0;
      cursor: pointer;
      font-size: 18px;
      line-height: 1;
    }

    .foldererror__items {
      margin: 0;
      padding-left: 16px;
    }

    .foldererror__file {
      margin-top: 6px;
    }

    .foldererror__places {
      margin: 4px 0 0 16px;
    }
  }

  .module-badge {
    display: inline-block;
    font-size: 11px;
    line-height: 1;
    padding: 2px 6px;
    margin-right: 6px;
    border: 1px solid $color__border;
    border-radius: 10px;
    color: $color__text--light;
  }

  .ownerline {
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }

  .owner-slug {
    color: $color__text--light;
    font-size: 12px;
  }
  .owners--scroll {
    max-height: 240px;
    overflow: auto;
    padding-right: 4px;
  }

  .owners-toggle {
    background: transparent;
    border: 0;
    padding: 0;
    cursor: pointer;
    color: $color__link;
    text-decoration: underline;
    font: inherit;
  }


  .owners-module {
    margin-top: 8px;
  }

  .module-toggle {
    background: transparent;
    border: 0;
    padding: 0;
    cursor: pointer;
    color: $color__text;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font: inherit;

    &:hover { text-decoration: underline; }
  }

  .module-toggle .chev {
    width: 1em;
    display: inline-block;
    text-align: center;
  }

  .module-toggle .count {
    color: $color__text--light;
    font-size: 12px;
  }

  .ownerslist {
    margin: 6px 0 0 22px;
    padding: 0;
    list-style: none;
  }

</style>
