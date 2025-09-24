module.exports = {
  root: true,
  env: {
    node: true
  },
  extends: ['plugin:vue/essential', '@vue/standard', 'prettier'],
  rules: {
    'no-unmodified-loop-condition':
      process.env.NODE_ENV === 'production' ? 'error' : 'warn',
    'no-unused-vars': process.env.NODE_ENV === 'production' ? 'error' : 'warn',
    'no-unreachable': process.env.NODE_ENV === 'production' ? 'error' : 'warn',
    'no-useless-escape': 0,
    'arrow-parens': 0,
    'generator-star-spacing': 0,
    'no-prototype-builtins': 0,
    'no-debugger': process.env.NODE_ENV === 'production' ? 2 : 0,
    'no-console':
      process.env.NODE_ENV === 'production'
        ? ['error', { allow: ['error'] }]
        : 'warn'
  },
  overrides: [
    {
      files: ['*.vue'],
      rules: {
        indent: 'off'
      }
    }
  ],
  parserOptions: {
    parser: '@babel/eslint-parser'
  }
}
