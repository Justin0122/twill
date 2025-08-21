<template>
  <div class="mediagrid">
    <div
      class="mediagrid__item"
      v-show="!item.isReplacement"
      v-for="(item, index) in itemsLoading"
      :key="'mediaLoading_' + item.id"
    >
      <span class="mediagrid__button s--loading">
        <span class="mediagrid__progress" v-if="!item.error"
        ><span
          class="mediagrid__progressBar"
          :style="loadingProgress(index)"
        ></span
        ></span>
        <span class="mediagrid__progressError" v-else>Upload Error</span>
      </span>
    </div>
    <div
      class="mediagrid__item"
      :class="{ 's--hasFilename': showFileName }"
      v-for="item in items"
      :key="item.id"
    >
      <span
        class="mediagrid__button"
        :class="{
          's--picked': selectedIdsSet.has(item.id),
          's--used': usedIdsSet.has(item.id) || !!replacingMediaIds[item.id],
          's--disabled': item.disabled
        }"
        :data-id="String(item.id)"
        data-ml-selectable
        @click.exact="toggleSelection(item)"
        @click.shift.exact="shiftToggleSelection(item)"
        @click.ctrl.exact="ctrlToggleSelection(item)"
        @click.meta.exact="ctrlToggleSelection(item)"
        @contextmenu.stop.prevent="openContextMenu($event, item)"
        draggable="true"
        @dragstart="onDragStart(item, $event)"
        @dragend="onDragEnd(item, $event)"
      >
        <img
          :src="item.thumbnail"
          class="mediagrid__img"
          loading="lazy"
          decoding="async"
          fetchpriority="low"
        />
      </span>
      <p v-if="showFileName" :title="item.name" class="mediagrid__name">
        {{ item.name }}
      </p>
    </div>

    <div
      v-if="contextMenu.open"
      ref="ctxmenu"
      class="mediagrid__ctxmenu"
      :style="{ left: contextMenu.x + 'px', top: contextMenu.y + 'px' }"
      role="menu"
      @click.stop
    >
      <button
        class="ctx-item danger"
        role="menuitem"
        @click="onCtxTrash"
        aria-label="Move to trash"
        title="Move to trash"
      >
        <svg class="icon" viewBox="0 0 24 24" aria-hidden="true" width="16" height="16">
          <path d="M3 6h18M8 6V4h8v2m-9 0h10l-1 14H8L7 6z"
                fill="none" stroke="currentColor" stroke-width="1.5" />
        </svg>
        <span class="label">Trash</span>
      </button>
    </div>
  </div>
</template>

<script>
  import { mapState } from 'vuex'
  import mediaItemsMixin from '@/mixins/mediaLibrary/mediaItems'

  export default {
    name: 'A17Mediagrid',
    mixins: [mediaItemsMixin],
    data() {
      return {
        contextMenu: {
          open: false,
          x: 0,
          y: 0,
          anchorId: null,
        }
      }
    },
    computed: {
      ...mapState({
        showFileName: state => state.mediaLibrary.showFileName
      }),
      selectedIdsSet() {
        return new Set((this.selectedItems || []).map(i => i.id))
      },
      usedIdsSet() {
        return new Set((this.usedItems || []).map(i => i.id))
      }
    },
    methods: {
      loadingProgress(index) {
        return {
          width: this.itemsLoading[index].progress
            ? this.itemsLoading[index].progress + '%'
            : '0%'
        }
      },
      ctrlToggleSelection(item) {
        this.$emit('ctrlChange', item)
      },
      isSelected(item, keys = ['id']) {
        if (keys.length === 1 && keys[0] === 'id') {
          return this.selectedIdsSet.has(item.id)
        }
        return Boolean(
          (this.selectedItems || []).find(sItem =>
            keys.every(key => sItem[key] === item[key])
          )
        )
      },
      isUsed(item, keys = ['id']) {
        if (keys.length === 1 && keys[0] === 'id') {
          return this.usedIdsSet.has(item.id)
        }
        return Boolean(
          (this.usedItems || []).find(uItem =>
            keys.every(key => uItem[key] === item[key])
          )
        )
      },
      onDragStart(item, evt) {
        if (item.disabled) return
        const selectedIds = (this.selectedItems || []).map(m => m.id)
        const ids =
          this.isSelected(item) && selectedIds.length ? selectedIds : [item.id]
        try {
          evt.dataTransfer.setData(
            'application/x-media-ids',
            JSON.stringify({ ids, type: this.type || null })
          )
        } catch (e) {
          evt.dataTransfer.setData('text/plain', JSON.stringify({ ids }))
        }
        evt.dataTransfer.effectAllowed = 'move'
      },
      onDragEnd() {
        this.$root.$emit('ml:dnd:hover:clear')
      },
      openContextMenu(evt, item) {
        const root = this.$el.getBoundingClientRect()
        const x = Math.min(evt.clientX - root.left, root.width - 160)
        const y = Math.min(evt.clientY - root.top, root.height - 44)

        this.contextMenu = {
          open: true,
          x,
          y,
          anchorId: item.id
        }

        document.addEventListener('click', this.closeContextMenuOnce, { once: true })
        document.addEventListener('keydown', this.closeOnEsc, { once: true })
      },
      closeContextMenuOnce: function() {
        this.contextMenu.open = false
      },
      closeOnEsc: function(e) {
        if (e.key === 'Escape') this.contextMenu.open = false
      },
      onCtxTrash() {
        if (!this.contextMenu.open) return

        const mediaIds = Array.from(this.selectedIdsSet)
        // eslint-disable-next-line no-console
        console.log('Moving to trash:', mediaIds)
        if (!mediaIds.length) {
          this.contextMenu.open = false
          return
        }

        this.$emit('move', {
          targetId: 'trash',
          mediaIds,
          type: this.type || null
        })

        this.contextMenu.open = false
      }
    }
  }
</script>

<style lang="scss" scoped>
  $height_text: 17px;

  .mediagrid {
    display: block;
    width: 100%;
    height: 100%;
    font-size: 0;
    line-height: 1em;
  }

  .mediagrid__ctxmenu {
    position: absolute;
    z-index: 1000;
    min-width: 140px;
    background: #fff;
    border: 1px solid $color__border--light;
    border-radius: 4px;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.12);
    padding: 4px;
  }
  .mediagrid__ctxmenu .ctx-item {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 6px 8px;
    background: transparent;
    border: 0;
    cursor: pointer;
    color: inherit;
    text-align: left;

    &:hover {
      background: $color__f--bg;
    }
  }
  .mediagrid__ctxmenu .ctx-item.danger {
    color: $color__error;
  }

  .mediagrid__ctxmenu .ctx-item .icon {
    flex: 0 0 auto;
    display: inline-block;
  }

  .mediagrid__ctxmenu .ctx-item .label {
    font-size: 12px;
    line-height: 16px;
  }

  .mediagrid__item {
    position: relative;
    display: inline-block;
    width: 16.66666665%;
    padding-bottom: 16.66666665%;
    overflow: hidden;
    background: white;
    contain: content;

    @media (max-width: 300px) {
      width: 100%;
      padding-bottom: 100%;
    }

    @media (min-width: 300px) {
      width: calc(100% / 2);
      padding-bottom: calc(100% / 2);
    }

    @media (min-width: 600px) {
      width: calc(100% / 2);
      padding-bottom: calc(100% / 2);
    }

    @media (min-width: 800px) {
      width: calc(100% / 3);
      padding-bottom: calc(100% / 3);
    }

    @media (min-width: 1000px) {
      width: calc(100% / 4);
      padding-bottom: calc(100% / 4);
    }

    @media (min-width: 1200px) {
      width: calc(100% / 5);
      padding-bottom: calc(100% / 5);
    }

    @media (min-width: 1400px) {
      width: calc(100% / 6);
      padding-bottom: calc(100% / 6);
    }

    @media (min-width: 1600px) {
      width: calc(100% / 7);
      padding-bottom: calc(100% / 7);
    }

    @media (min-width: 1800px) {
      width: calc(100% / 8);
      padding-bottom: calc(100% / 8);
    }

    @media (min-width: 2000px) {
      width: calc(100% / 9);
      padding-bottom: calc(100% / 9);
    }

    @media (min-width: 2200px) {
      width: calc(100% / 10);
      padding-bottom: calc(100% / 10);
    }

    &.s--hasFilename {
      @media (max-width: 300px) {
        width: 100%;
        padding-bottom: calc(100% + #{$height_text});
      }

      @media (min-width: 300px) {
        padding-bottom: calc((100% / 2) + #{$height_text});
      }

      @media (min-width: 600px) {
        padding-bottom: calc((100% / 2) + #{$height_text});
      }

      @media (min-width: 800px) {
        padding-bottom: calc((100% / 3) + #{$height_text});
      }

      @media (min-width: 1000px) {
        padding-bottom: calc((100% / 4) + #{$height_text});
      }

      @media (min-width: 1200px) {
        padding-bottom: calc((100% / 5) + #{$height_text});
      }

      @media (min-width: 1400px) {
        padding-bottom: calc((100% / 6) + #{$height_text});
      }

      @media (min-width: 1600px) {
        padding-bottom: calc((100% / 7) + #{$height_text});
      }

      @media (min-width: 1800px) {
        padding-bottom: calc((100% / 8) + #{$height_text});
      }

      @media (min-width: 2000px) {
        padding-bottom: calc((100% / 9) + #{$height_text});
      }

      @media (min-width: 2200px) {
        padding-bottom: calc((100% / 10) + #{$height_text});
      }
    }
  }

  .mediagrid__button {
    position: absolute;
    cursor: pointer;

    display: flex;
    justify-content: center; /* align horizontal */
    flex-direction: column;
    align-items: center; /* align vertical */
    @include font-regular;

    user-select: none;

    top: 10px;
    left: 10px;
    right: 10px;
    bottom: 10px;

    .s--hasFilename & {
      bottom: calc(10px + #{$height_text});
    }

    &:before {
      content: '';
      position: absolute;
      display: block;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      border: 1px solid rgba(0, 0, 0, 0.05);
    }

    &.s--picked {
      &:after {
        content: '';
        position: absolute;
        display: block;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        border: 4px solid $color__link;
        z-index: 1;
      }
    }

    &.s--used {
      &:before {
        content: '';
        position: absolute;
        display: block;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: $color__translucentBlue;
        opacity: 0.85;
      }
    }

    &.s--disabled {
      pointer-events: none;
      opacity: 0.2;
    }
  }

  .mediagrid__name {
    position: absolute;
    bottom: 0;
    right: 0;
    left: 0;
    padding: 3px 20px;
    white-space: nowrap;
    text-overflow: ellipsis;
    overflow: hidden;
    font-size: 13px;
    color: $color__text--light;
    width: 100%;
    text-align: center;
    cursor: default;
  }

  .s--loading {
    background: $color__f--bg;
    cursor: default;
  }

  .mediagrid__img {
    display: block;
    max-width: 100%;
    height: auto;
    max-height: 100%;
  }

  .mediagrid__progress {
    height: 4px;
    width: 80%;
    background: $color__border--focus;
    border-radius: 2px;
    position: relative;
  }

  .mediagrid__progressBar {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    border-radius: 2px;
    height: 4px;
    background: $color__action;
  }

  .mediagrid__progressError {
    color: $color__error;
  }
</style>
