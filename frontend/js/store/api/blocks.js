import axios from 'axios'

import { globalError } from '@/utils/errors'

export default {
  getBlockPreview(endpoint, data, callback, errorCallback) {
    axios.post(endpoint, data).then(
      function(resp) {
        if (callback && typeof callback === 'function') callback(resp.data)
      },
      function(resp) {
        const error = {
          message: 'Block preview request error.',
          value: resp
        }
        globalError('CONTENT', error)
        if (errorCallback && typeof errorCallback === 'function')
          errorCallback(resp)
      }
    )
  },

  saveGrid(url, payload, onSuccess, onError) {
    axios.post(url, payload)
      .then(res => onSuccess && onSuccess(res.data))
      .catch(err => onError && onError(err))
  }
}
