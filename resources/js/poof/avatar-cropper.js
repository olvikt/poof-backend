import Cropper from 'cropperjs'

export default ({ photo }) => ({
    photo,
    cropper: null,

    init() {
        this.$watch('photo', (value) => {
            if (!value) return
            this.$nextTick(() => this.initCropper())
        })
    },

    initCropper() {
        if (this.cropper) {
            this.cropper.destroy()
        }

        const image = this.$refs.image
        if (!image) return

        this.cropper = new Cropper(image, {
            aspectRatio: 1,
            viewMode: 1,
            autoCropArea: 1,
            background: false,
            guides: false,
            movable: true,
            zoomable: true,
            cropBoxResizable: false,
        })
    },

    save() {
        if (!this.cropper) return

        this.cropper.getCroppedCanvas({ width: 400, height: 400 })
            .toBlob(blob => {
                if (!blob) return
                this.$dispatch('avatar-cropped', {
                    file: new File([blob], 'avatar.jpg', { type: 'image/jpeg' })
                })
            })
    }
})
