import { createStore } from 'vuex'

import mediaLibrary from './modules/media-library'
import notification from './modules/notification'
import previewPayloads from '@/store/modules/previewPayloads'

const debug = process.env.NODE_ENV !== 'production'

export default createStore({
  modules: {
    notification,
    mediaLibrary,
    previewPayloads
  },
  strict: debug
})
