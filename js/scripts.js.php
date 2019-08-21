<?php
/**
 * ---------------------------------------------------------------------
 * Formcreator is a plugin which allows creation of custom forms of
 * easy access.
 * ---------------------------------------------------------------------
 * LICENSE
 *
 * This file is part of Formcreator.
 *
 * Formcreator is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Formcreator is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Formcreator. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 * @copyright Copyright © 2011 - 2019 Teclib'
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @link      https://github.com/pluginsGLPI/formcreator/
 * @link      https://pluginsglpi.github.io/formcreator/
 * @link      http://plugins.glpi-project.org/#/plugin/formcreator
 * ---------------------------------------------------------------------
 */

include ('../../../inc/includes.php');
header('Content-Type: text/javascript');
?>

var modalWindow;
var rootDoc          = CFG_GLPI['root_doc'];
var currentCategory  = "0";
var sortByName = false;
var tiles = [];
var serviceCatalogEnabled = false;
var slinkyCategories;
var timers = [];

// === COMMON ===

function getTimer(object) {
   return function(timeout, action) {
      var timer;
      object.keyup(
         function(event) {
            if (typeof timer != 'undefined') {
               clearTimeout(timer);
            }
            if (event.which == 13) {
               action();
            } else {
               timer = setTimeout(function() {
                  action();
               }, timeout);
            }
         }
      );
   }
}

// === MENU ===
var link = '';
link += '<li id="menu7">';
link += '<a href="' + rootDoc + '/plugins/formcreator/front/formlist.php" class="itemP">';
link += "<?php echo Toolbox::addslashes_deep(_n('Form', 'Forms', 2, 'formcreator')); ?>";
link += '</a>';
link += '</li>';

$(function() {
   var target = $('body');
   modalWindow = $("<div></div>").dialog({
      width: 980,
      autoOpen: false,
      height: "auto",
      modal: true,
      position: ['center', 50],
      open: function( event, ui ) {
         //remove existing tinymce when reopen modal (without this, tinymce don't load on 2nd opening of dialog)
         modalWindow.find('.mce-container').remove();
      }
   });

   // toggle menu in desktop mode
   $('#formcreator-toggle-nav-desktop').change(function() {
      $('.plugin_formcreator_container').toggleClass('toggle_menu');
      $.ajax({
         url: rootDoc + '/plugins/formcreator/ajax/homepage_wizard.php',
         data: {wizard: 'toggle_menu'},
         type: "POST",
         dataType: "json"
      })
   });

   serviceCatalogEnabled = $("#plugin_formcreator_serviceCatalog").length;

   // Prevent jQuery UI dialog from blocking focusin
   $(document).on('focusin', function(e) {
       if ($(e.target).closest(".mce-window, .moxman-window").length) {
         e.stopImmediatePropagation();
      }
   });

   <?php
   if (isset($_SESSION['glpiactiveprofile']['interface'])
       && ($_SESSION['glpiactiveprofile']['interface'] == 'helpdesk')
       && PluginFormcreatorForm::countAvailableForm() > 0) {
      echo "$('#c_menu #menu1:first-child').after(link);";
   }
   ?>

   if (location.pathname.indexOf("helpdesk.public.php") != -1) {

      $('.ui-tabs-panel:visible').ready(function() {
         showHomepageFormList();
      });

      $('#tabspanel + div.ui-tabs').on("tabsload", function(event, ui) {
         showHomepageFormList();
      });

      showHomepageFormList();

   } else if ($('#plugin_formcreator_wizard_categories').length > 0) {
      updateCategoriesView();
      updateWizardFormsView(0);
      $("#wizard_seeall").parent().addClass('category_active');

      // Setup events
      $('.plugin_formcreator_sort input[value=mostPopularSort]').click(function () {
         sortByName = false;
         showTiles(tiles);
      });

      $('.plugin_formcreator_sort input[value=alphabeticSort]').click(function () {
         sortByName = true;
         showTiles(tiles);
      });

      $('#plugin_formcreator_wizard_categories #wizard_seeall').click(function () {
         slinkyCategories.home();
         updateWizardFormsView(0);
         $('#plugin_formcreator_wizard_categories .category_active').removeClass('category_active');
         $(this).addClass('category_active');
      });
   }

   // Initialize search bar
   searchInput = $('#plugin_formcreator_searchBar input:first');
   if (searchInput.length == 1) {
      // Dynamically update forms and faq items while the user types in the search bar
      var timer = getTimer(searchInput);
      var callback = function() {
         updateWizardFormsView(currentCategory);
      }
      timer(300, callback);
      timers.push(timer);

      // Clear the search bar if it gains focus
      $('#plugin_formcreator_searchBar input').focus(function(event) {
         if (searchInput.val().length > 0) {
            searchInput.val('');
            updateWizardFormsView(currentCategory);
            $.when(getFormAndFaqItems(0)).then(
               function (response) {
                  tiles = response;
                  showTiles(tiles.forms);
               }
            );
         }
      });

      // Initialize sort controls
      $('.plugin_formcreator_sort input[value=mostPopularSort]')[0].checked = true;
   }

   // === Add better multi-select on form configuration validators ===
   // initialize the pqSelect widget.
      fcInitMultiSelect();

   $('#tabspanel + div.ui-tabs').on("tabsload", function( event, ui ) {
      fcInitMultiSelect();
   });

});

function fcInitMultiSelect() {
   $("#validator_users").select2();
   $("#validator_groups").select2();
}

function showHomepageFormList() {
   if ($('.homepage_forms_container').length) {
      return;
   }

   $.ajax({
      url: rootDoc + '/plugins/formcreator/ajax/homepage_forms.php',
      type: "GET"
   }).done(function(response){
      if (!$('.homepage_forms_container').length) {
         $('.central > tbody:first').first().prepend(response);
      }
   });
}

function updateCategoriesView() {
   $.ajax({
      url: rootDoc + '/plugins/formcreator/ajax/homepage_wizard.php',
      data: {wizard: 'categories'},
      type: "GET",
      dataType: "json"
   }).done(function(response) {
      html = '<div class="slinky-menu">';
      html = html + buildCategoryList(response);
      html = html + '</div>';

      //Display categories
      $('#plugin_formcreator_wizard_categories .slinky-menu').remove();
      $('#plugin_formcreator_wizard_categories').append(html);

      // Setup slinky
      slinkyCategories = $('#plugin_formcreator_wizard_categories div:nth(2)').slinky({
         label: true
      });
      $('#plugin_formcreator_wizard_categories a.back').click(
         function(event) {
            parentItem = $(event.target).parentsUntil('#plugin_formcreator_wizard_categories > div', 'li')[1];
            parentAnchor = $(parentItem).children('a')[0];
            updateWizardFormsView(parentAnchor.getAttribute('data-parent-category-id'));
         }
      );

      $('#plugin_formcreator_wizard_categories a[data-category-id]').click(
         function (event) {
            $('#plugin_formcreator_wizard_categories .category_active').removeClass('category_active');
            $(this).addClass('category_active');
            updateWizardFormsView(event.target.getAttribute('data-category-id'));
         }
      );
   });
}

/**
 * get form and faq items from DB
 * Returns a promise
 */
function getFormAndFaqItems(categoryId) {
   currentCategory = categoryId;
   keywords = $('#plugin_formcreator_searchBar input:first').val();
   deferred = jQuery.Deferred();
   $.ajax({
      url: rootDoc + '/plugins/formcreator/ajax/homepage_wizard.php',
      data: {wizard: 'forms', categoriesId: categoryId, keywords: keywords, helpdeskHome: 0},
      type: "GET",
      dataType: "json"
   }).done(function (response) {
      deferred.resolve(response);
   }).fail(function (response) {
      deferred.reject();
   });
   return deferred.promise();
}

function sortFormAndFaqItems(items, byName) {
   if (byName == true) {
      // sort by name
      items.sort(function (a, b) {
         if (a.name < b.name) {
            return -1;
         }
         if (a.name > b.name) {
            return 1
         }
         return 0;
      });
   } else {
      // sort by view or usage count
      items.sort(function (a, b) {
         if (a.usage_count > b.usage_count) {
            return -1;
         }
         if (a.usage_count < b.usage_count) {
            return 1
         }
         return 0;
      });
   }
   return items;
}

function showTiles(tiles, defaultForms) {
   tiles = sortFormAndFaqItems(tiles, sortByName);
   html = '';
   if (defaultForms) {
      html += '<p><?php echo Toolbox::addslashes_deep(__('No form found. Please choose a form below instead', 'formcreator'))?></p>'
   }
   html += buildTiles(tiles);

   //Display tiles
   $('#plugin_formcreator_wizard_forms').empty();
   $('#plugin_formcreator_wizard_forms').prepend(html);
   $('#plugin_formcreator_formlist').masonry({
      horizontalOrder: true
   });
   $('#plugin_formcreator_faqlist').masonry({
      horizontalOrder: true
   });
}

function updateWizardFormsView(categoryId) {
   $.when(getFormAndFaqItems(categoryId)).done(
      function (response) {
         tiles = response.forms;
         showTiles(tiles, response.default);
      }
   ).fail(
      function () {
         html = '<p><?php echo Toolbox::addslashes_deep(__('An error occured while querying forms', 'formcreator'))?></p>'
         $('#plugin_formcreator_wizard_forms').empty();
         $('#plugin_formcreator_wizard_forms').prepend(html);
         $('#plugin_formcreator_formlist').masonry({
            horizontalOrder: true
         });
         $('#plugin_formcreator_faqlist').masonry({
            horizontalOrder: true
         });
      }
   );
}

function buildCategoryList(tree) {
   if (tree.id != 0) {
      html = '<a href="#" data-parent-category-id="' + tree.parent +'"'
         + ' data-category-id="' + tree.id + '"'
         + ' onclick="updateWizardFormsView(' + tree.id + ')">'
         + tree.name
         + '</a>';
   } else {
      html = '';
   }
   if (Object.keys(tree.subcategories).length == 0) {
      return html;
   }
   html = html + '<ul>';
   $.each(tree.subcategories, function (key, val) {
      html = html + '<li>' + buildCategoryList(val) + '</li>';
   });
   html = html + '</ul>';
   return html;
}

function buildTiles(list) {
   $(document).on('click', '.plugin_formcreator_formTile', function(){
      document.location = $(this).children('a').attr('href');
   });

   if (list.length == 0) {
      html = '<p id="plugin_formcreator_formlist">'
      + "<?php echo Toolbox::addslashes_deep(__('No form yet in this category', 'formcreator')) ?>"
      + '</p>'
      +'<p id="plugin_formcreator_faqlist"></p>';
   } else {
      var forms = [];
      var faqs = [];
      $.each(list, function (key, item) {
         // Build a HTML tile
         if (item.type == 'form') {
            url = rootDoc + '/plugins/formcreator/front/formdisplay.php?id=' + item.id;
         } else {
            if (serviceCatalogEnabled) {
               url = rootDoc + '/plugins/formcreator/front/knowbaseitem.form.php?id=' + item.id;
            } else {
               url = rootDoc + '/front/knowbaseitem.form.php?id=' + item.id;
            }
         }

         description = '';
         if (item.description) {
            description = '<div class="plugin_formcreator_formTile_description">'
                          +item.description
                          +'</div>';
         }

         var default_class = '';
         if (JSON.parse(item.is_default)) {
            default_class = 'default_form';
         }

         if (item.icon == '') {
            if (item.type == 'form') {
               item.icon = 'fa fa-question-circle';
            } else {
               item.icon = 'fa fa-clipboard-list';
            }
         }

         if (item.icon_color == '') {
            item.icon_color = '#999999';
         }

         if (item.type == 'form') {
            forms.push(
               '<div style="background-color: ' + item.background_color + '" class="plugin_formcreator_formTile '+item.type+' '+default_class+'" title="'+item.description+'">'
               + '<i class="fa ' + item.icon + '" style="color: ' + item.icon_color+ '"></i>'
               + '<a href="' + url + '" class="plugin_formcreator_formTile_title">'
               + item.name
               + '</a>'
               + description
               + '</div>'
            );
         } else {
            faqs.push(
               '<div style="background-color: ' + item.background_color + '" class="plugin_formcreator_formTile '+item.type+' '+default_class+'" title="'+item.description+'">'
               + '<i class="fa ' + item.icon + '" style="color: ' + item.icon_color+ '"></i>'
               + '<a href="' + url + '" class="plugin_formcreator_formTile_title">'
               + item.name
               + '</a>'
               + description
               + '</div>'
            );
         }
      });

      // concatenate all HTML parts
      html = '<div id="plugin_formcreator_formlist">'
      + forms.join("")
      + '</div><div id="plugin_formcreator_faqlist">'
      + faqs.join("")
      + '</div>'
   }

   return html;
}

// === SEARCH BAR ===

// === QUESTIONS ===
var urlQuestion      = rootDoc + "/plugins/formcreator/ajax/question.php";
var urlFrontQuestion = rootDoc + "/plugins/formcreator/front/question.form.php";

function plugin_formcreator_addQuestion(items_id, token, section) {
   modalWindow.load(urlQuestion, {
      section_id: section,
      _glpi_csrf_token: token
   })
   .dialog("open");
}

function plugin_formcreator_editQuestion(items_id, token, question, section) {
   modalWindow.load(urlQuestion, {
      question_id: question,
      section_id: section,
      _glpi_csrf_token: token
   }).dialog("open");
}

function setRequired(token, question_id, val) {
   jQuery.ajax({
     url: urlFrontQuestion,
     type: "POST",
     data: {
         set_required: 1,
         id: question_id,
         value: val,
         _glpi_csrf_token: token
      }
   }).done(reloadTab);
}

function moveQuestion(token, question_id, action) {
   jQuery.ajax({
     url: urlFrontQuestion,
     type: "POST",
     data: {
         move: 1,
         id: question_id,
         way: action,
         _glpi_csrf_token: token
      }
   }).done(reloadTab);
}

function plugin_formcreator_deleteQuestion(items_id, token, question_id) {
   if(confirm("<?php echo Toolbox::addslashes_deep(__('Are you sure you want to delete this question?', 'formcreator')); ?> ")) {
      jQuery.ajax({
        url: urlFrontQuestion,
        type: "POST",
        data: {
            id: question_id,
            delete_question: 1,
            plugin_formcreator_forms_id: items_id,
            _glpi_csrf_token: token
         }
      }).done(reloadTab);
   }
}

function duplicateQuestion(items_id, token, question_id) {
   jQuery.ajax({
     url: urlFrontQuestion,
     type: "POST",
     data: {
         id: question_id,
         duplicate_question: 1,
         plugin_formcreator_forms_id: items_id,
         _glpi_csrf_token: token
      }
   }).done(reloadTab);
}


// === SECTIONS ===
var urlSection      = rootDoc + "/plugins/formcreator/ajax/section.php";
var urlFrontSection = rootDoc + "/plugins/formcreator/front/section.form.php";

function plugin_formcreator_addSection(items_id, token) {
   modalWindow.load(urlSection, {
      form_id: items_id,
      _glpi_csrf_token: token
   }).dialog("open");
}

function plugin_formcreator_editSection(items_id, token ,section) {
   modalWindow.load(urlSection, {
      section_id: section,
      form_id: items_id,
      _glpi_csrf_token: token
   }).dialog("open");
}

function duplicateSection(items_id, token, section_id) {
   jQuery.ajax({
     url: urlFrontSection,
     type: "POST",
     data: {
         duplicate_section: 1,
         id: section_id,
         plugin_formcreator_forms_id: items_id,
         _glpi_csrf_token: token
      }
   }).done(reloadTab);
}

function plugin_formcreator_deleteSection(items_id, token, section_id) {
   if(confirm("<?php echo Toolbox::addslashes_deep(__('Are you sure you want to delete this section?', 'formcreator')); ?> ")) {
      jQuery.ajax({
        url: urlFrontSection,
        type: "POST",
        data: {
            delete_section: 1,
            id: section_id,
            plugin_formcreator_forms_id: items_id,
            _glpi_csrf_token: token
         }
      }).done(reloadTab);
   }
}

function moveSection(token, section_id, action) {
   jQuery.ajax({
     url: urlFrontSection,
     type: "POST",
     data: {
         move: 1,
         id: section_id,
         way: action,
         _glpi_csrf_token: token
      }
   }).done(reloadTab);
}


// === TARGETS ===
function plugin_formcreator_addTarget(items_id, token) {
   modalWindow.load(rootDoc + '/plugins/formcreator/ajax/target.php', {
      form_id: items_id,
      _glpi_csrf_token: token
   }).dialog("open");
}

function plugin_formcreator_deleteTarget(itemtype, target_id, token) {
   if(confirm("<?php echo Toolbox::addslashes_deep(__('Are you sure you want to delete this destination:', 'formcreator')); ?> ")) {
      jQuery.ajax({
        url: rootDoc + '/plugins/formcreator/front/form.form.php',
        type: "POST",
        data: {
            delete_target: 1,
            itemtype: itemtype,
            items_id: target_id,
            _glpi_csrf_token: token
         }
      }).done(function () {
         location.reload();
      });

   }
}

// SHOW OR HIDE FORM FIELDS
var formcreatorQuestions = new Object();

function formcreatorShowFields(form) {
   $.ajax({
      url: rootDoc + '/plugins/formcreator/ajax/showfields.php',
      type: "POST",
      data: form.serializeArray()
   }).done(function(response){
      try {
         var questionToShow = JSON.parse(response);
      } catch (e) {
         // Do nothing for now
      }
      var i = 0;
      for (var questionKey in questionToShow) {
         var questionId = questionKey;
         questionId = parseInt(questionKey.replace('formcreator_field_', ''));
         if (!isNaN(questionId)) {
            if (questionToShow[questionKey]) {
               $('#form-group-field-' + questionKey).show();
               i++;
               $('#form-group-field-' + questionKey).removeClass('line' + (i+1) % 2);
               $('#form-group-field-' + questionKey).addClass('line' + i%2);
            } else {
               $('#form-group-field-' + questionKey).hide();
               $('#form-group-field-' + questionKey).removeClass('line0');
               $('#form-group-field-' + questionKey).removeClass('line1');
            }
         }
      }
   });
}

// DESTINATION
function plugin_formcreator_formcreatorChangeDueDate(value) {
   $('#due_date_questions').hide();
   $('#due_date_time').hide();
   switch (value) {
      case '1' :
         $('#due_date_questions').show();
         break;
      case '2' :
         $('#due_date_time').show();
         break;
      case '3' :
         $('#due_date_questions').show();
         $('#due_date_time').show();
         break;
   }
}

function plugin_formcreator_displayRequesterForm() {
   $('#form_add_requester').show();
   $('#btn_add_requester').hide();
   $('#btn_cancel_requester').show();
}

function plugin_formcreator_hideRequesterForm() {
   $('#form_add_requester').hide();
   $('#btn_add_requester').show();
   $('#btn_cancel_requester').hide();
}

function plugin_formcreator_displayWatcherForm() {
   $('#form_add_watcher').show();
   $('#btn_add_watcher').hide();
   $('#btn_cancel_watcher').show();
}

function plugin_formcreator_hideWatcherForm() {
   $('#form_add_watcher').hide();
   $('#btn_add_watcher').show();
   $('#btn_cancel_watcher').hide();
}

function plugin_formcreator_displayAssignedForm() {
   $('#form_add_assigned').show();
   $('#btn_add_assigned').hide();
   $('#btn_cancel_assigned').show();
}

function plugin_formcreator_hideAssignedForm() {
   $('#form_add_assigned').hide();
   $('#btn_add_assigned').show();
   $('#btn_cancel_assigned').hide();
}

function plugin_formcreator_ChangeActorRequester(value) {
   $('#block_requester_user').hide();
   $('#block_requester_question_user').hide();
   $('#block_requester_group').hide();
   $('#block_requester_question_group').hide();
   $('#block_requester_question_actors').hide();
   $('#block_requester_supplier').hide();
   $('#block_requester_question_supplier').hide();

   switch (value) {
      case '3' : $('#block_requester_user').show();               break;
      case '4' : $('#block_requester_question_user').show();      break;
      case '5' : $('#block_requester_group').show();              break;
      case '6' : $('#block_requester_question_group').show();     break;
      case '9' : $('#block_requester_question_actors').show();    break;
      case '7' : $('#block_requester_supplier').show();           break;
      case '8' : $('#block_requester_question_supplier').show();  break;
   }
}

function plugin_formcreator_ChangeActorWatcher(value) {
   $('#block_watcher_user').hide();
   $('#block_watcher_question_user').hide();
   $('#block_watcher_group').hide();
   $('#block_watcher_question_group').hide();
   $('#block_watcher_question_actors').hide();
   $('#block_watcher_supplier').hide();
   $('#block_watcher_question_supplier').hide();

   switch (value) {
      case '3' : $('#block_watcher_user').show();               break;
      case '4' : $('#block_watcher_question_user').show();      break;
      case '5' : $('#block_watcher_group').show();              break;
      case '6' : $('#block_watcher_question_group').show();     break;
      case '9' : $('#block_watcher_question_actors').show();    break;
      case '7' : $('#block_watcher_supplier').show();           break;
      case '8' : $('#block_watcher_question_supplier').show();  break;
   }
}

function plugin_formcreator_ChangeActorAssigned(value) {
   $('#block_assigned_user').hide();
   $('#block_assigned_question_user').hide();
   $('#block_assigned_group').hide();
   $('#block_assigned_question_group').hide();
   $('#block_assigned_question_actors').hide();
   $('#block_assigned_supplier').hide();
   $('#block_assigned_question_supplier').hide();

   // The numbers match PluginFormcreatorTarget_Actor::ACTOR_TYPE_* constants
   switch (value) {
      case '3' : $('#block_assigned_user').show();               break;
      case '4' : $('#block_assigned_question_user').show();      break;
      case '5' : $('#block_assigned_group').show();              break;
      case '6' : $('#block_assigned_question_group').show();     break;
      case '9' : $('#block_assigned_question_actors').show();    break;
      case '7' : $('#block_assigned_supplier').show();           break;
      case '8' : $('#block_assigned_question_supplier').show();  break;
   }
}

// === FIELDS EDITION ===

function plugin_formcreator_addEmptyCondition(target) {
   var questionId = $('form[name="form_question"] input[name="id"]').val();
   var sectionId = $('form[name="form_question"] [name="plugin_formcreator_sections_id"]').val();
   $.ajax({
      url: rootDoc + '/plugins/formcreator/ajax/question_condition.php',
      data: {
         plugin_formcreator_questions_id: questionId,
         plugin_formcreator_sections_id: sectionId,
         _empty: ''
      }
   }).done(function (data) {
      $(target).parents('tr').after(data);
      $('.plugin_formcreator_logicRow .div_show_condition_logic').first().hide();
   });
}

function plugin_formcreator_removeNextCondition(target) {
   $(target).parents('tr').remove();
   $('.plugin_formcreator_logicRow .div_show_condition_logic').first().hide();
}

function plugin_formcreator_changeDropdownItemtype(rand) {
   dropdown_type = $('[name="form_question"] [name="dropdown_values"]').val();

   $.ajax({
      url: rootDoc + '/plugins/formcreator/ajax/dropdown_values.php',
      type: 'GET',
      data: {
         dropdown_itemtype: dropdown_type,
      },
   }).done(function(response) {
      showTicketCategorySpecific = false;
      if (dropdown_type == 'ITILCategory') {
         showTicketCategorySpecific = true;
      }
      $('#dropdown_default_value_field').html(response);
      $('.plugin_formcreator_dropdown_ticket').toggle(showTicketCategorySpecific);
   });
}

function plugin_formcreator_changeGlpiObjectItemType() {
   glpi_object = $('[name="form_question"] [name="glpi_objects"]').val();

   $.ajax({
      url: rootDoc + '/plugins/formcreator/ajax/dropdown_values.php',
      type: 'GET',
      data: {
         dropdown_itemtype: glpi_object,
      },
   }).done(function(response) {
      $('#dropdown_default_value_field').html(response);
   });
}

function plugin_formcreator_toggleCondition(field) {
   if (field.value == '1') {
      $('.plugin_formcreator_logicRow').hide();
   } else {
      if ($('.plugin_formcreator_logicRow').length < 1) {
         plugin_formcreator_addEmptyCondition(field);
      }
      $('.plugin_formcreator_logicRow').show();
   }
}

function toggleLogic(field) {
   if (field.value == '0') {
      $('#'+field.id).parents('tr').next().remove();
   } else {
      plugin_formcreator_addEmptyCondition(field);
   }
}

// === FIELDS ===

/**
 * Initialize a simple field
 */
function pluginFormcreatorInitializeField(fieldName, rand) {
   var field = $('input[name="' + fieldName + '"]');
   var timer = getTimer(field);
   var callback = function() {
      formcreatorShowFields($(field[0].form));
   }
   timer(300, callback);
   timers.push(timer);
}

/**
 * Initialize an actor field
 */
function pluginFormcreatorInitializeActor(fieldName, rand, initialValue) {
   var field = $('select[name="' + fieldName + '[]"]');
   field.select2({
      tokenSeparators: [",", ";"],
      minimumInputLength: 0,
      tags: true,
      ajax: {
         url: rootDoc + "/ajax/getDropdownUsers.php",
         type: "POST",
         dataType: "json",
         data: function (params, page) {
            return {
               entity_restrict: -1,
               searchText: params.term,
               page_limit: 100,
               page: page
            }
         },
         results: function (data, page) {
            var more = (data.count >= 100);
            return {results: data.results, pagination: {"more": more}};
         }
      },
      createTag: function(params) {
         var term = $.trim(params.term);

         if (term == '') {
            return null;
         }

         return {
            id: term,
            text: term,
            newTag: true
         }
      },
   });
   initialValue = JSON.parse(initialValue);
   for (var i = 0; i < initialValue.length; i++) {
      var option = new Option(initialValue[i].text, initialValue[i].id, true, true);
      field.append(option).trigger('change');
      field.trigger({
         type: 'select2.select',
         params: {
            data: initialValue[i]
         }
      });
   }
   field.on("change", function(e) {
      formcreatorShowFields($(field[0].form));
   });
}

/**
 * Initialize a checkboxes field
 */
function pluginFormcreatorInitializeCheckboxes(fieldName, rand) {
   var field = $('input[name="' + fieldName + '[]"]');
   field.on("change", function() {
      formcreatorShowFields($(field[0].form));
   });
}

/**
 * Initialize a date field
 */
function pluginFormcreatorInitializeDate(fieldName, rand) {
   var field = $('input[name="_' + fieldName + '"]');
   field.on("change", function() {
      formcreatorShowFields($(field[0].form));
   });
   $('#resetdate' + rand).on("click", function() {
      formcreatorShowFields($(field[0].form));
   });
}

/**
 * Initialize a dropdown field
 */
function pluginFormcreatorInitializeDropdown(fieldName, rand) {
   var field = $('select[name="' + fieldName + '"]');
   field.on("change", function(e) {
      formcreatorShowFields($(field[0].form));
   });
}

/**
 * Initialize a email field
 */
function pluginFormcreatorInitializeEmail(fieldName, rand) {
   var field = $('input[name="' + fieldName + '"]');
   var timer = getTimer(field);
   var callback = function() {
      formcreatorShowFields($(field[0].form));
   }
   timer(300, callback);
   timers.push(timer);
}

/**
 * Initialize a radios field
 */
function pluginFormcreatorInitializeRadios(fieldName, rand) {
   var field = $('input[name="' + fieldName + '"]');
   field.on("change", function() {
      formcreatorShowFields($(field[0].form));
   });
}

/**
 * Initialize a multiselect field
 */
function pluginFormcreatorInitializeMultiselect(fieldName, rand) {
   var field = $('select[name="' + fieldName + '[]"]');
   field.on("change", function() {
      formcreatorShowFields($(field[0].form));
   });
}

/**
 * Initialize a select field
 */
function pluginFormcreatorInitializeSelect(fieldName, rand) {
   var field = $('select[name="' + fieldName + '"]');
   field.on("change", function() {
      formcreatorShowFields($(field[0].form));
   });
}

/**
 * Initialize a tag field
 */
function pluginFormcreatorInitializeTag(fieldName, rand) {
   var field = $('select[name="' + fieldName + '[]"]');
   field.on("change", function(e) {
      formcreatorShowFields($(field[0].form));
   });
}

/**
 * Initialize a textarea field
 */
function pluginFormcreatorInitializeTextarea(fieldName, rand) {
   var field = $('input[name="' + fieldName + '"]');
   field.on("change", function(e) {
      formcreatorShowFields($(field[0].form));
   });
}

/**
 * Initialize a urgency field
 */
function pluginFormcreatorInitializeUrgency(fieldName, rand) {
   var field = $('select[name="' + fieldName + '"]');
   field.on("change", function(e) {
      formcreatorShowFields($(field[0].form));
   });
}

function plugin_formcreator_changeQuestionType(rand) {
   var questionId = $('form[name="form_question"] input[name="id"]').val();
   var questionType = $ ('form[name="form_question"] [name="fieldtype"]').val();

   $.ajax({
      url: rootDoc + '/plugins/formcreator/ajax/question_design.php',
      type: 'GET',
      data: {
         questionId: questionId,
         questionType: questionType,
      },
   }).done(function(response) {
      try {
         response = $.parseJSON(response);
      } catch (e) {
         console.log('Plugin Formcreator: Failed to get subtype fields');
         return;
      }

      $('#label_required').toggle(response.may_be_required);
      $('#plugin_formcreator_required > *').toggle(response.may_be_required);

      $('#label_show_empty').toggle(response.may_be_empty);
      $('#plugin_formcreator_show_empty > *').toggle(response.may_be_empty);

      $('#plugin_formcreator_subtype_label').html(response.label);
      $('#plugin_formcreator_subtype_value').html(response.field);

      plugin_formcreator_updateQuestionSpecific(response.additions);
   });
}

function plugin_formcreator_updateQuestionSpecific(html) {
   $('.plugin_formcreator_question_specific:gt(0)').remove();
   if (html == '') {
      $('.plugin_formcreator_question_specific').hide();
      return;
   }
   $('.plugin_formcreator_question_specific').replaceWith(html);
}

function plugin_formcreator_changeLDAP(ldap) {
   var ldap_directory = ldap.value;

   jQuery.ajax({
   url: rootDoc + '/plugins/formcreator/ajax/ldap_filter.php',
   type: 'POST',
   data: {
         value: ldap_directory,
      },
   }).done(function(response) {
      document.getElementById('ldap_filter').value = response;
   });
}

/**
 * preview of the selected pictogram
 */
function plugin_formceator_showPictogram(id, preview) {
   var value = $(id).val();
   $('#' + preview).removeClass().addClass(value);
}

/**
 * update composite ticket (links between tickets) in target ticket (design mode)
 */
function plugin_formcreator_updateCompositePeerType(rand) {
   if ($('#dropdown__link_itemtype' + rand).val() == 'Ticket') {
      $('#plugin_formcreator_link_ticket').show();
      $('#plugin_formcreator_link_target').hide();
   } else {
      $('#plugin_formcreator_link_ticket').hide();
      $('#plugin_formcreator_link_target').show();
   }
}

/**
 * update category settings in a form of a target object (design mode)
 */
 function plugin_formcreator_changeCategory(rand) {
   $('#category_specific_title').hide();
   $('#category_specific_value').hide();
   $('#category_question_title').hide();
   $('#category_question_value').hide();

   switch($('#dropdown_category_rule' + rand).val()) {
      case '3' :
         $('#category_question_title').show();
         $('#category_question_value').show();
         break;
      case '2' :
         $('#category_specific_title').show();
         $('#category_specific_value').show();
         break;
   }
}

/**
 * change urgency of a target item (design mode)
 */
function plugin_formcreator_changeUrgency(rand) {
   $('#urgency_specific_title').hide();
   $('#urgency_specific_value').hide();
   $('#urgency_question_title').hide();
   $('#urgency_question_value').hide();

   switch($('#dropdown_urgency_rule' + rand).val()) {
      case '2' :
         $('#urgency_question_title').show();
         $('#urgency_question_value').show();
         break;
      case '3':
         $('#urgency_specific_title').show();
         $('#urgency_specific_value').show();
         break;
   }
}

function plugin_formcreator_change_associate(rand) {
   $('#plugin_formcreator_associate_specific_title').hide();
   $('#plugin_formcreator_associate_specific_value').hide();
   $('#plugin_formcreator_associate_question_title').hide();
   $('#plugin_formcreator_associate_question_value').hide();

   switch($('#dropdown_associate_rule' + rand).val()) {
      case '3': // PluginFormcreatorTargetTicket::ASSOCIATE_RULE_ANSWER
         $('#plugin_formcreator_associate_question_title').show();
         $('#plugin_formcreator_associate_question_value').show();
         break;
      case '2': // PluginFormcreatorTargetTicket::ASSOCIATE_RULE_SPECIFIC
         $('#plugin_formcreator_associate_specific_title').show();
         $('#plugin_formcreator_associate_specific_value').show();
         break;
   }
}

function plugin_formcreator_change_location(rand) {
   $('#location_specific_title').hide();
   $('#location_specific_value').hide();
   $('#location_question_title').hide();
   $('#location_question_value').hide();

   switch($('#dropdown_location_rule' + rand).val()) {
      case '3' : // PluginFormcreatorTargetBase::CATEGORY_RULE_ANSWER
         $('#location_question_title').show();
         $('#location_question_value').show();
         break;
      case '2' : // PluginFormcreatorTargetBase::CATEGORY_RULE_SPECIFIC
         $('#location_specific_title').show();
         $('#location_specific_value').show();
         break;
   }
}

function plugin_formcreator_change_entity(rand) {
   $('#entity_specific_title').hide();
   $('#entity_user_title').hide();
   $('#entity_entity_title').hide();
   $('#entity_specific_value').hide();
   $('#entity_user_value').hide();
   $('#entity_entity_value').hide();

   switch($('#dropdown_destination_entity' + rand).val()) {
      case '7' : // PluginFormcreatorTargetBase::DESTINATION_ENTITY_SPECIFIC
         $('#entity_specific_title').show();
         $('#entity_specific_value').show();
         break;
      case '8' : // PluginFormcreatorTargetBase::DESTINATION_ENTITY_USER
         $('#entity_user_title').show();
         $('#entity_user_value').show();
         break;
      case '9' : // PluginFormcreatorTargetBase::DESTINATION_ENTITY_ENTITY
         $('#entity_entity_title').show();
         $('#entity_entity_value').show();
         break;
   }
}


