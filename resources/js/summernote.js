import $ from "jquery";
window.$ = window.jQuery = $;

// 2) Summernote (Bootstrap 5 build)
import "summernote/dist/summernote-lite.css";
import "summernote/dist/summernote-lite.js";

$(document).ready(function () {
    // track the currently focused editor
    let activeEditor = null;

    // initialize *every* editor on the page
    $(".summernote-editor").each(function () {
        const $el = $(this);

        $el.summernote({
            placeholder: "Hello stand alone ui",
            tabsize: 2,
            height: 200,
            toolbar: [
                ["style", ["style"]],
                ["font", ["bold", "underline", "clear"]],
                ["color", ["color"]],
                ["para", ["ul", "ol", "paragraph"]],
                ["table", ["table"]],
                ["insert", ["link", "picture", "video"]],
                ["view", ["fullscreen", "codeview", "help"]],
            ],
            callbacks: {
                onFocus() {
                    // mark this editor as the active one
                    activeEditor = this;
                },
                onImageUpload(files) {
                    uploadSummernoteImage(files[0], this);
                },
                onMediaDelete(target) {
                    deleteSummernoteImage(target[0].src);
                },
            },
        });

        const colorBtn = document.getElementById("insertColorBtn");
        const colorPicker = document.getElementById("colorPicker");

        // only wire up the picker if both elements are present
        if (colorBtn && colorPicker) {
            // ensure it's absolutely positioned
            Object.assign(colorPicker.style, {
                position: "absolute",
                display: "none",
                zIndex: 9999,
            });

            colorBtn.addEventListener("click", () => {
                const rect = colorBtn.getBoundingClientRect();
                colorPicker.style.top = `${rect.bottom + window.scrollY}px`;
                colorPicker.style.left = `${rect.left + window.scrollX}px`;
                colorPicker.style.display = "block";
                colorPicker.click();
            });

            // insert into #summernote when a color is picked
            $("#colorPicker").on("input", function () {
                colorPicker.style.display = "none";
                const color = this.value;
                const $btn = $("<button>")
                    .attr("type", "button")
                    .text("Button")
                    .css({
                        "background-color": color,
                        padding: "5px",
                        "border-radius": "5px",
                        border: "none",
                        color: "#fff",
                        cursor: "pointer",
                    });
                const $wrapper = $(
                    '<div style="text-align:center;"></div>'
                ).append($btn);
                $(".summernote-editor").summernote("insertNode", $wrapper[0]);
            });
        }
    });
});
function setLoading(isLoading) {
    const btn = $("#save_button");
    if (isLoading) {
        btn.prop("disabled", true).find(".spinner").remove();
        btn.prepend(
            '<span class="spinner" style="display:inline-block;width:1rem;height:1rem;border:2px solid currentColor;border-top-color:transparent;border-radius:50%;animation:spin .6s linear infinite;margin-right:.5rem;"></span>'
        );
    } else {
        btn.prop("disabled", false).find(".spinner").remove();
    }
}

// Upload & insert image into Summernote
function uploadSummernoteImage(file, editor) {
    setLoading(true);
    const form = new FormData();
    form.append("image", file);
    const token = $('meta[name="csrf-token"]').attr("content");

    axios
        .post("/admin/upload-summernote-image", form, {
            headers: { "X-CSRF-TOKEN": token },
        })
        .then(({ data }) => {
            if (data.url) {
                $(editor).summernote("insertImage", data.url);
            } else {
                alert("Upload failed: no URL returned.");
            }
        })
        .catch((err) => {
            console.error("Image upload failed:", err);
            alert("Failed to upload image.");
        })
        .finally(() => setLoading(false));
}

// Delete image when removed from Summernote
function deleteSummernoteImage(src) {
    const token = $('meta[name="csrf-token"]').attr("content");
    axios
        .delete("/admin/delete-summernote-image", {
            data: { url: src },
            headers: { "X-CSRF-TOKEN": token },
        })
        .then(() => console.log("Deleted image", src))
        .catch((err) => console.error("Image deletion failed:", err));
}

function destroyEditors() {
    $(".summernote-editor").each(function () {
        console.log("destroy");

        const $el = $(this);
        if ($el.next().hasClass("note-editor")) {
            $el.summernote("destroy");
            console.log("destroy2");
        }
    });
}

// 2) Initialize all editors
function initializeEditorForPage(id) {
    setTimeout(() => {
        $(".editor-" + id).summernote({
            placeholder: "Page content…",
            tabsize: 2,
            height: 200,
            toolbar: [
                ["style", ["style"]],
                ["font", ["bold", "underline", "clear"]],
                ["color", ["color"]],
                ["para", ["ul", "ol", "paragraph"]],
                ["insert", ["link", "picture", "video"]],
                ["view", ["fullscreen", "codeview", "help"]],
            ],
            callbacks: {
                onImageUpload(files) {
                    uploadSummernoteImage(files[0], this);
                },
                onMediaDelete(target) {
                    deleteSummernoteImage(target[0].src);
                },
            },
        });
    }, 50);
}

window.destroyEditors = destroyEditors;
window.initializeEditorForPage = initializeEditorForPage;
