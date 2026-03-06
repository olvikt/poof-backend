export default function collapse(Alpine) {
  Alpine.directive('collapse', (el, { expression }, { effect }) => {
    const duration = 250

    el.style.overflow = 'hidden'
    el.style.transition = `height ${duration}ms ease`

    effect(() => {
      if (expression) {
        el.style.height = `${el.scrollHeight}px`
      } else {
        el.style.height = '0px'
      }
    })
  })
}
