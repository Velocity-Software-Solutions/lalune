<!-- Simplicity is the ultimate sophistication. - Leonardo da Vinci -->
<div class="w-full max-w-xl mx-auto">
    <label for="video" class="block text-sm font-medium text-gray-700 dark:text-white mb-2">Upload Video</label>

    <div
         x-ref="dropzone"
         @dragover.prevent="isDragging = true"
         @dragleave="isDragging = false"
         @drop.prevent="handleDrop($event); isDragging = false"
         @click="$refs.video.click()"
         :class="isDragging
                    ? 'bg-violet-100 border-violet-500 dark:bg-violet-900 dark:border-violet-400'
                    : 'bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-white'"
         class="flex items-center justify-center w-full h-48 rounded-lg cursor-pointer border-solid border-2 transition duration-150"
    >
        <div class="text-center">
            <span class="material-icons large text-gray-500 dark:text-white">
                arrow_upward
            </span>
            <p class="text-lg font-bold text-gray-600 dark:text-white mt-2">Drag Here</p>
            <p x-show="fileName" class="mt-2 text-sm font-medium text-gray-800 dark:text-white" x-text="fileName"></p>
<template x-if="errors.video">
    <div class="text-red-500 text-sm mt-1" x-text="errors.video[0]"></div>
</template>
        </div>

        <input
            type="file"
            name="video"
            x-ref="video"
            @change="fileName = $event.target.files[0]?.name"
            class="hidden"
        />
    </div>
</div>
