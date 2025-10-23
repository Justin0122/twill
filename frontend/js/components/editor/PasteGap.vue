<template>
  <div
    class="pasteGap"
    :class="{ 'pasteGap--armed': armed }"
    role="button"
    tabindex="0"
    @mouseenter="arm"
    @mouseleave="disarm"
    @focus="arm"
    @blur="disarm"
    @keydown.stop="onKeydown"
    @click.stop="$emit('paste-here')"
    :title="$trans('fields.block-editor.paste-here', 'Paste here')"
  >
    <transition name="pasteGap-fade">
      <div v-if="armed" class="pasteGap__inner">
        <div class="pasteGap__icon" aria-hidden="true">📋</div>
        <div class="pasteGap__text">
          <strong>{{ $trans('fields.block-editor.paste-here', 'Paste here') }}</strong>
          <span class="pasteGap__hint">
            {{ $trans('fields.block-editor.paste-block', 'Paste block') }}
          </span>
        </div>
      </div>
    </transition>
  </div>
</template>
<script>
  export default {
    name: 'PasteGap',
    emits: ['paste-here'],
    data: () => ({ armed: false, timer: null }),
    methods: {
      arm () {
        if (this.timer) clearTimeout(this.timer)
        this.timer = setTimeout(() => (this.armed = true), 1000)
      },
      disarm () {
        if (this.timer) clearTimeout(this.timer)
        this.timer = null
        this.armed = false
      },
      onKeydown (e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 'v' || e.key === 'V')) {
          e.preventDefault()
          this.$emit('paste-here')
        }
      }
    },
    beforeUnmount () {
      if (this.timer) clearTimeout(this.timer)
    }
  }
</script>
<style lang="scss" scoped>
  .pasteGap {
    position: relative;
    width: 100%;
    height: 44px;
    margin: 12px 0;
    border-radius: 10px;
    background: rgba(0,0,0,0.03);
    border: 1px dashed rgba(0,0,0,0.15);
    display: block;
    transition:
      height 200ms ease,
      background 150ms ease,
      border-color 150ms ease,
      box-shadow 150ms ease;
    cursor: pointer;
    outline: none;

    &:hover,
    &:focus {
      background: rgba(0,0,0,0.05);
      border-color: rgba(0,0,0,0.25);
      box-shadow: 0 0 0 2px rgba(0,0,0,0.05) inset;
    }

    &--armed {
      height: 120px;
      background: rgba(0,0,0,0.06);
      border-color: rgba(0,0,0,0.3);
    }

    &__inner {
      pointer-events: none;
      display: grid;
      grid-template-columns: 24px 1fr auto;
      gap: 12px;
      align-items: center;
      width: 100%;
      padding: 10px 14px;
    }

    &__icon { font-size: 18px; opacity: 0.8; }
    &__text { display: flex; flex-direction: column; gap: 2px; }
    &__hint { font-size: 12px; opacity: 0.75; }

    &__btn {
      pointer-events: auto;
      appearance: none;
      border: 0;
      border-radius: 8px;
      padding: 6px 10px;
      line-height: 1;
      background: rgba(0,0,0,0.75);
      color: #fff;
      font-weight: 600;
      transition: transform 120ms ease;
    }

    &:hover &__btn,
    &:focus &__btn,
    &--armed &__btn { transform: translateY(-1px); }
  }

  .pasteGap-fade-enter-active,
  .pasteGap-fade-leave-active {
    transition: opacity 180ms ease, transform 180ms ease;
  }
  .pasteGap-fade-enter-from,
  .pasteGap-fade-leave-to {
    opacity: 0;
    transform: translateY(6px);
  }
</style>
