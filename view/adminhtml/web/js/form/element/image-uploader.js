/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
  "jquery",
  "underscore",
  "uiRegistry",
  "Magento_Ui/js/form/element/image-uploader",
  "Magento_Ui/js/modal/modal",
  "Magento_PageBuilder/js/resource/resize-observer/ResizeObserver",
  "Magento_PageBuilder/js/events",
  "mage/translate",
], function ($, _, uiRegistry, Uploader, modal, ResizeObserver, events, $t) {
  "use strict";

  var initializedOnce = false;

  jQuery("body").append(
    '<div id="popup-modal" style="display:none;"><div id="compactViewContainer"></div></div>'
  );

  return Uploader.extend({
    defaults: {
      $uploadArea: null,
      isShowImageUploadInstructions: true,
      isShowImageUploadOptions: false,
      visibleControls: true,
      classes: {
        dragging: "dragging",
        draggingInside: "dragging-inside",
        draggingOutside: "dragging-outside",
      },
      // listed in ascending order
      elementWidthModifierClasses: {
        "_micro-ui": {
          maxWidth: 130,
        },
        "_compact-ui": {
          minWidth: 131,
          maxWidth: 440,
        },
      },
      translations: {
        allowedFileTypes: $t("Allowed file types"),
        dragImageHere: $t("Drag image here"),
        dropHere: $t("Drop here"),
        maximumFileSize: $t("Maximum file size"),
        bynderImage: $t("Bynder Image"),
        or: $t('or'),
        uploadImage: $t('Upload Image'),
        uploadNewImage: $t('Upload New Image'),
        selectFromGallery: $t('Select from Gallery')
      },
      tracks: {
        visibleControls: true,
      },
    },

    /**
     * Bind drag events to highlight/unhighlight dropzones
     * {@inheritDoc}
     */
    initialize: function () {
      var $document = $(document);

      this._super();

      events.on(
        "image:" + this.id + ":assignAfter",
        this.onAssignedFile.bind(this)
      );

      // bind dropzone highlighting using event delegation only once
      if (!initializedOnce) {
        // dropzone highlighting
        $document.on("dragover", this.highlightDropzone.bind(this));

        // dropzone unhighlighting
        $document.on(
          "dragend dragleave mouseup",
          this.unhighlightDropzone.bind(this)
        );

        initializedOnce = true;
      }
    },

    /**
     * {@inheritDoc}
     */
    initUploader: function (fileInput) {
      this._super(fileInput);
      this.$uploadArea = $(this.$fileInput).closest(
        ".pagebuilder-image-empty-preview"
      );
      new ResizeObserver(this.updateResponsiveClasses.bind(this)).observe(
        this.$uploadArea.get(0)
      );
    },

    /**
     * Checks if provided file is allowed to be uploaded.
     * {@inheritDoc}
     */
    isFileAllowed: function () {
      var result = this._super(),
        allowedExtensions =
          this.getAllowedFileExtensionsInCommaDelimitedFormat();

      if (!result.passed && result.rule === "validate-file-type") {
        result.message +=
          " " +
          this.translations.allowedFileTypes +
          ": " +
          allowedExtensions +
          ".";
      }

      return result;
    },

    /**
     * Remove draggable classes from dropzones
     * {@inheritDoc}
     */
    onBeforeFileUpload: function () {
      this.removeDraggableClassesFromDropzones();
      this._super();
    },

    /**
     * Add/remove CSS classes to $dropzone element to provide UI feedback
     *
     * @param {jQuery.event} e
     */
    highlightDropzone: function (e) {
      var $dropzone = $(e.target).closest(this.dropZone),
        $otherDropzones = $(this.dropZone).not($dropzone),
        isInsideDropzone = !!$dropzone.length;

      if (isInsideDropzone) {
        $dropzone
          .removeClass(this.classes.draggingOutside)
          .addClass(
            [this.classes.dragging, this.classes.draggingInside].join(" ")
          );
      }

      $otherDropzones
        .removeClass(this.classes.draggingInside)
        .addClass(
          [this.classes.dragging, this.classes.draggingOutside].join(" ")
        );
    },

    /**
     * Remove all UI styling from dropzone
     *
     * @param {jQuery.event} e
     */
    unhighlightDropzone: function (e) {
      var isMouseReleased = e.type === "mouseup" || e.type === "dragend",
        isActuallyLeavingThePage =
          e.type === "dragleave" && (e.clientX === 0 || e.clientY === 0);

      if (!isMouseReleased && !isActuallyLeavingThePage) {
        return;
      }

      this.removeDraggableClassesFromDropzones();
    },

    /**
     * Remove draggable CSS classes from dropzone elements
     */
    removeDraggableClassesFromDropzones: function () {
      var $dropzones = $(this.dropZone);

      $dropzones.removeClass(
        [
          this.classes.dragging,
          this.classes.draggingInside,
          this.classes.draggingOutside,
        ].join(" ")
      );
    },

    /**
     * Trigger image:uploadAfter event to be handled by PageBuilder image component
     * {@inheritDoc}
     */
    addFile: function (file) {
      this._super();

      events.trigger("image:" + this.id + ":uploadAfter", [file]);

      return this;
    },

    /**
     * Trigger image:deleteFileAfter event to be handled by PageBuilder image component
     * {inheritDoc}
     */
    clear: function () {
      this._super();

      events.trigger("image:" + this.id + ":deleteFileAfter");

      return this;
    },

    /**
     * Propagate file changes through all image uploaders sharing the same id
     *
     * @param {Object} file
     */
    onAssignedFile: function (file) {
      this.value([file]);
    },

    /**
     * Adds the appropriate ui state class to the upload control area based on the current rendered size
     */
    updateResponsiveClasses: function () {
      var classesToAdd = [],
        classConfig,
        elementWidth = this.$uploadArea.width(),
        modifierClass;

      if (!this.$uploadArea.is(":visible")) {
        return;
      }

      this.$uploadArea.removeClass(
        Object.keys(this.elementWidthModifierClasses).join(" ")
      );

      for (modifierClass in this.elementWidthModifierClasses) {
        if (!this.elementWidthModifierClasses.hasOwnProperty(modifierClass)) {
          // jscs:disable disallowKeywords
          continue;
          // jscs:enable disallowKeywords
        }

        classConfig = this.elementWidthModifierClasses[modifierClass];

        if (
          (classConfig.minWidth &&
            classConfig.maxWidth &&
            classConfig.minWidth <= elementWidth &&
            elementWidth <= classConfig.maxWidth) ||
          (classConfig.minWidth &&
            !classConfig.maxWidth &&
            classConfig.minWidth <= elementWidth) ||
          (classConfig.maxWidth &&
            !classConfig.minWidth &&
            elementWidth <= classConfig.maxWidth)
        ) {
          classesToAdd.push(modifierClass);
        }
      }

      if (classesToAdd.length) {
        this.$uploadArea.addClass(classesToAdd.join(" "));
      }
    },

    /**
     * {@inheritDoc}
     */
    hasData: function () {
      // Some of the components automatically add an empty object if the value is unset.
      return this._super() && !$.isEmptyObject(this.value()[0]);
    },

    /**
     * Stop event to prevent it from reaching any objects other than the current object.
     *
     * @param {Object} uploader
     * @param {Event} event
     * @returns {Boolean}
     */
    stopEvent: function (uploader, event) {
      event.stopPropagation();

      return true;
    },
    clickBynderImage: function () {
      var btn_class = jQuery(".cms_bynder_action_btn").attr("class");
      var exp = btn_class.split(" ");
      var b_url = exp[exp.length - 1];
      var base_url = window.location.href;
      var delimiter = "/";
      var arrayOfStrings = base_url.split(delimiter);
      console.log(arrayOfStrings);
      var AjaxUrl =
        arrayOfStrings[0] + "//" + arrayOfStrings[2] + "/bynder/index";
      var callurl =
        arrayOfStrings[0] + "//" + arrayOfStrings[2] + "/bynder/bynderindex";
      var product_id = arrayOfStrings[8];
      console.log(AjaxUrl);
      var docicon = "https://img.icons8.com/cotton/2x/regular-document.png";
      var p_id = jQuery(".cms_bynder_action_btn").parent().parent().attr("id");
      var ident = "#";
      if (p_id == "" || p_id == undefined) {
        var res = jQuery(".cms_bynder_action_btn")
          .parent()
          .parent()
          .attr("class");
        if (res != undefined && res != "") {
          res = res.split(" ");
          res = Array.from(res);
          p_id = res[0];
          ident = ".";
        } else {
          p_id = undefined;
          return false;
        }
      }
      BynderCompactView.open({
        /* mode:"SingleSelect", */
        mode: "MultiSelect",
        assetTypes: ["image", /* "audio", */ "video", "document"],
        onSuccess: function (assets, additionalInfo) {
          console.log("Successfull Bynder Click...");
          var result = assets[0];
          var image_path = result.derivatives.webImage;
          console.log("assets");
          console.log(assets);

          var server_response = bynder_media_func(assets, additionalInfo);
          if (server_response) {
            return true;
          } else {
            return false;
          }

          function bynder_media_func(assets, a) {
            var asset = assets[0];
            var dataset_ids = [];
            var dataset_type = [];
            var video_url = [];

            $.each(assets, function (index, value) {
              dataset_ids.push(value.databaseId);
              dataset_type.push(value.type);
              if (value.__typename == "Video") {
                video_url[value.databaseId] = value.previewUrls[0];
              }
            });

            var bdomain = localStorage.getItem("cvad");
            if (typeof bdomain == "undefined" && bdomain == null) {
              alert("Something went wrong. Re-login system and try again...");
            }
            console.log(dataset_ids);
            console.log(dataset_type);
            console.log(video_url);

            $.ajax({
              showLoader: true,
              url: AjaxUrl,
              type: "POST",
              data: {
                databaseId: dataset_ids,
                bdomain: bdomain,
                datasetType: dataset_type,
              },
              dataType: "json",
            }).done(function (data) {
              console.log(data);
			
              var total_images = 0;
              if (data.status == 2) {
                alert(data.message);
                return false;
              } else if (data.status == 1) {
                var type_design = "";
                type_design +=
                  '<div class="main-part bynder-imgbox-div">' +
                  '<div class="middle-content">' +
                  '<div class="main-one image-boxs" >';

                $.each(data.data, function (index, r) {
                  $.each(r, function (i, res) {
                    var dataset_tag = '<img src="' + res.image_link + '">';
                    total_images++;
					
                    if (res.dataset_type == "VIDEO") {
                      dataset_tag =
                        '<video width="100%" controls><source src="' +
                        res.image_link +
                        '" type="video/mp4"><source src="' +
                        res.main_link +
                        '" type="video/ogg">Your browser does not support HTML video.</video>';
                    }

                    var dataset_size = "( Size: " + res.size + ")";
                    if (res.size == "0x0") {
                      dataset_size = " ";
                    }

                    if (res.size == "0x0" && res.dataset_type == "DOCUMENT") {
                      type_design +=
                        '<div class="m-box">' +
                        '<div class="m-img">' +
                        dataset_tag +
                        "</div>" +
                        '<div class="m-content">' +
                        '<input type="checkbox" class="image_types" id="image_type_' +
                        total_images +
                        '" name="image_type_' +
                        index +
                        '" value="' +
                        res.type +
                        index +
                        '">' +
                        '<label for="image_type_' +
                        total_images +
                        '">' +
                        res.type +
                        " " +
                        dataset_size +
                        "</label>" +
                        "</div>" +
                        "</div>";
                    }

                    if (
                      res.dataset_type == "IMAGE" ||
                      res.dataset_type == "VIDEO"
                    ) {
                      if (res.size != "0x0") {
                        type_design +=
                          '<div class="m-box">' +
                          '<div class="m-img">' +
                          dataset_tag +
                          "</div>" +
                          '<div class="m-content">' +
                          '<input type="checkbox" class="image_types" id="image_type_' +
                          total_images +
                          '" name="image_type_' +
                          index +
                          '" value="' +
                          res.type +
                          index +
                          '">' +
                          '<label for="image_type_' +
                          total_images +
                          '">' +
                          res.type +
                          " " +
                          dataset_size +
                          "</label>" +
                          "</div>" +
                          "</div>";
                      }
                    }
                  });
                });
                type_design += "</div> </div> </div>";
                $("#compactViewContainer").html(type_design);
                var tag_html = "";
                var options = {
                  type: "popup",
                  responsive: true,
                  innerScroll: true,
                  title: "Select Bynder Image",
                  buttons: [
                    {
                      text: $.mage.__("Continue"),
                      id: "selected_item_btn",
                      class: "bynder_cms_button",
                      click: function () {
                        var selected_types = [];
                        $(".image_types").each(function () {
                          var select_val = $(this).val();
                          if ($(this).prop("checked")) {
                            selected_types.push(select_val);
                          }
                        });

                        var database_videos_array = [];
                        if (selected_types.length > 0) {
                          var img_url = "";
                          var img_url_path = "";
                          var database_array = [];
                          $.each(data.data, function (index, r) {
                            var image_links_test = assets[index].url;
                            $.each(r, function (i, res) {
                              image_links_test +=
                                "&&thumb_link=" + res.image_link + "&&";
                              var type_val = res.type + index;
                              if ($.inArray(type_val, selected_types) != -1) {
                                console.log(res);
                                if (res.dataset_type == "IMAGE") {
                                  database_array.push(res.public_url);
                                }
                              }
                            });
                          });

                          console.log(database_array);

                          addtodirectory(database_array);
                        } else {
                          alert("Sorry, you not selected any type ?");
                        }
                        this.closeModal();
                      },
                    },
                  ],
                };
                var popup = modal(options, $("#popup-modal"));
                $("#popup-modal").modal("openModal");
                return true;
              } else {
              }
            });
          }
          function addtodirectory(img_data) {
            console.log("add dir");
            console.log(img_data);
            var form_keys = $("#form_keys").val();
            var dir_path = "idex/";
            if (img_data != "") {
              $.ajax({
                showLoader: true,
                url: callurl,
                type: "POST",
                data: {
                  img_data: img_data,
                  form_key: form_keys,
                  dir_path: dir_path,
                },
                dataType: "json",
              }).done(function (data) {
                var res = data;
                console.log("image_data", data);
					$('figure').find('.pagebuilder-image-uploader-container').find('.pagebuilder-options-middle').css('display','block');
					$('figure').find('.pagebuilder-image-uploader-container').find('.pagebuilder-image-empty-preview').css('display','none');
                if (res.status == 1) {
                  jQuery("figure").append(
                    '<img src=" ' +
                      arrayOfStrings[0] +
                      "//" +
                      arrayOfStrings[2] +
                      "/media/wysiwyg/" +
                      dir_path + '/' +
                      res.file_name +
                      '" class="bynder-view" >'
                  ).trigger('imageAdded');
                  jQuery("#YnluZGVy a").click();
                  return true;
                } else {
                  alert(res.message);
                  return false;
                }
              });
            } else {
              alert(
                "Something went wrong. Please reload the page and try again."
              );
              return false;
            }
          }
        },
      });
    },
  });
});
