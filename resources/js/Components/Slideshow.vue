<script setup>
import { ref, onMounted, onBeforeUnmount, defineProps } from 'vue'

// Defaults to images served from public/images/Slideshow (capital S)
const props = defineProps({
  images: {
    type: Array,
    default: () => [
      '/images/Slideshow/s1.webp',
      '/images/Slideshow/s3.webp',
      '/images/Slideshow/s4.webp',
      '/images/Slideshow/s2.webp',
      '/images/Slideshow/s5.webp',
      '/images/Slideshow/s6.webp',
    ],
  },
  interval: { type: Number, default: 3500 },
})

const currentIndex = ref(0)
const loadedImages = ref([])
let timer = null

function preload(src) {
  return new Promise((resolve) => {
    const img = new Image()
    img.onload = () => resolve(src)
    img.onerror = () => resolve(null)
    img.src = src
  })
}

async function loadImages() {
  // Build candidates with fallbacks (.webp -> .jpg -> .png)
  const candidates = []
  props.images.forEach((p) => {
    const j = p.replace(/\.webp$/i, '.jpg')
    const png = p.replace(/\.webp$/i, '.png')
    candidates.push(p)
    if (j !== p) candidates.push(j)
    if (png !== p && png !== j) candidates.push(png)
  })
  const unique = [...new Set(candidates)]
  // Load sequentially; show the first valid image immediately
  for (const src of unique) {
    const ok = await preload(src)
    if (ok) {
      loadedImages.value.push(ok)
      // Start the slideshow once we have 2+ images
      if (!timer && loadedImages.value.length > 1) start()
    }
  }
}

const start = () => {
  if (loadedImages.value.length <= 1) return
  stop()
  timer = setInterval(() => {
    currentIndex.value = (currentIndex.value + 1) % loadedImages.value.length
  }, props.interval)
}

const stop = () => {
  if (timer) {
    clearInterval(timer)
    timer = null
  }
}

onMounted(async () => {
  await loadImages()
  if (!timer && loadedImages.value.length > 0) start()
})
onBeforeUnmount(stop)
</script>

<template>
  <div class="relative w-full h-[420px] md:h-[640px] overflow-hidden rounded-2xl">
    <template v-if="loadedImages.length">
      <img :src="loadedImages[currentIndex]" alt="Slideshow image" class="absolute inset-0 w-full h-full object-cover" />
    </template>
    <template v-else>
      <div class="absolute inset-0 bg-gray-100" />
    </template>

    <div v-if="loadedImages.length > 1" class="absolute bottom-3 left-0 right-0 flex justify-center gap-1.5">
      <span v-for="(img, i) in loadedImages" :key="i" class="h-1.5 w-1.5 rounded-full" :class="i === currentIndex ? 'bg-white' : 'bg-white/60'" />
    </div>
  </div>
</template>


