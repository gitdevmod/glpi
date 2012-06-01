<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2012 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

// ----------------------------------------------------------------------
// Original Author of file:
// Purpose of file:
// ----------------------------------------------------------------------

define('GLPI_ROOT', '..');
include (GLPI_ROOT . "/inc/includes.php");

Session::checkCentralAccess();

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

if (isset($_GET['multiple_actions'])) {
   if (isset($_SESSION['glpi_massiveaction']) && isset($_SESSION['glpi_massiveaction']['POST'])) {
      $_POST = $_SESSION['glpi_massiveaction']['POST'];
   }
}

if (!isset($_POST["itemtype"]) || !($item = getItemForItemtype($_POST["itemtype"]))) {
   exit();
}


/// Right check
switch ($_POST["itemtype"]) {
   case 'Ticket' :
      switch ($_POST["action"]) {
         case "delete" :
            Session::checkRight("delete_ticket", "1");
            break;

         case "add_followup" :
            Session::checkSeveralRightsOr(array('global_add_followups' => 1,
                                                'own_ticket'           => 1));
            break;

         case "add_task" :
            Session::checkSeveralRightsOr(array('global_add_tasks' => 1,
                                                'own_ticket'       => 1));
            break;

         default :
            Session::checkRight("update_ticket", "1");
      }
      break;

   default :
      if (in_array($_POST["itemtype"],$CFG_GLPI["infocom_types"])) {
         Session::checkSeveralRightsOr(array($_POST["itemtype"] => 'w',
                                             'infocom'          => 'w'));
      } else {
         $item->checkGlobal('w');
      }
}

Html::header(__('Bulk modification'), $_SERVER['PHP_SELF']);

if (isset($_GET['multiple_actions'])) {
   if (isset($_SESSION['glpi_massiveaction'])
       && isset($_SESSION['glpi_massiveaction']['items'])) {

      $percent = min(100,round(100*($_SESSION['glpi_massiveaction']['item_count']
                                    - count($_SESSION['glpi_massiveaction']['items']))
                                 /$_SESSION['glpi_massiveaction']['item_count'],0));
      Html::displayProgressBar(400, $percent);
   }
}

if (isset($_POST["action"])
    && isset($_POST["itemtype"])
    && isset($_POST["item"])
    && count($_POST["item"])) {

   /// Save selection
   if (!isset($_SESSION['glpimassiveactionselected'])
       || count($_SESSION['glpimassiveactionselected']) == 0) {

      $_SESSION['glpimassiveactionselected'] = array();
      foreach ($_POST["item"] as $key => $val) {
         if ($val == 1) {
            $_SESSION['glpimassiveactionselected'][$key] = $key;
         }
      }
   }

   if (isset($_SERVER['HTTP_REFERER'])) {
      $REDIRECT = $_SERVER['HTTP_REFERER'];
   } else { /// Security : not used if no problem
      $REDIRECT = $CFG_GLPI['root_doc']."/front/central.php";
   }

   $nbok      = 0;
   $nbnoright = 0;
   $nbko      = 0;

   switch($_POST["action"]) {
      case "transform_to" :
         if (!empty($_POST["transform_to"])) {
            $networkport = new NetworkPort();
            foreach ($_POST["item"] as $key => $val) {
               if ($val == 1) {
                  if ($networkport->can($key,'w') && $item->can($key,'d')) {
                     if ($networkport->switchInstantiationType($_POST['transform_to']) !== false) {
                        $instantiation             = $networkport->getInstantiation();
                        $input                     = $item->fields;
                        $input['networkports_id']  = $input['id'];
                        unset($input['id']);
                        if ($instantiation->add($input)) {
                           $item->delete(array('id' => $key));
                        } else {
                           $nbko++;
                        }
                     } else {
                        $nbko++;
                     }
                  } else {
                     $nbnoright++;
                  }
               }
            }
         } else {
             $nbko++;
         }
         break;

      case "connect_to_computer" :
         if (isset($_POST["connect_item"]) && $_POST["connect_item"]) {
            $conn = new Computer_Item();
            foreach ($_POST["item"] as $key => $val) {
               if ($val == 1) {
                  $input = array('computers_id' => $key,
                                 'itemtype'     => $_POST["itemtype"],
                                 'items_id'     => $_POST["connect_item"]);
                  if ($conn->can(-1, 'w', $input)) {
                     if ($conn->add($input)) {
                        $nbok++;
                     } else {
                        $nbko++;
                     }
                  } else {
                     $nbnoright++;
                  }
               }
            }
         }
         break;

      case "connect" :
         if (isset($_POST["connect_item"]) && $_POST["connect_item"]) {
            $conn = new Computer_Item();
            foreach ($_POST["item"] as $key => $val) {
               if ($val == 1) {
                  $input = array('computers_id' => $_POST["connect_item"],
                                 'itemtype'     => $_POST["itemtype"],
                                 'items_id'     => $key);
                  if ($conn->can(-1, 'w', $input)) {
                     if ($conn->add($input)) {
                        $nbok++;
                     } else {
                        $nbko++;
                     }
                  } else {
                     $nbnoright++;
                  }
               }
            }
         }
         break;

      case "disconnect" :
         $conn = new Computer_Item();
         foreach ($_POST["item"] as $key => $val) {
            if ($val == 1) {
               if ($item->can($key, 'd')) {
                  if ($conn->disconnectForItem($item)) {
                     $nbok++;
                  } else {
                     $nbko++;
                  }
               } else {
                  $nbnoright++;
               }
            }
         }
         break;

      case "delete" :
         foreach ($_POST["item"] as $key => $val) {
            if ($val == 1) {
               if ($item->can($key,'d')){
                  if ($item->delete(array("id" => $key))) {
                     $nbok++;
                  } else {
                     $nbko++;
                  }
               } else {
                  $nbnoright++;
               }
            }
         }
         break;

      case "purge" :
         foreach ($_POST["item"] as $key => $val) {
            if ($val == 1) {
               if ($item->can($key,'d')){
                  if ($item->delete(array("id" => $key), 1)) {
                     $nbok++;
                  } else {
                     $nbko++;
                  }
               } else {
                  $nbnoright++;
               }
            }
         }
         break;

      case "restore" :
         foreach ($_POST["item"] as $key => $val) {
            if ($val == 1) {
               if ($item->can($key,'d')){
                  if ($item->restore(array("id" => $key))) {
                     $nbok++;
                  } else {
                     $nbko++;
                  }
               } else {
                  $nbnoright++;
               }
            }
         }
         break;

      case "update" :
         $searchopt = Search::getCleanedOptions($_POST["itemtype"],'w');
         if (isset($searchopt[$_POST["id_field"]])) {
            /// Infocoms case
            if (!isPluginItemType($_POST["itemtype"])
                && Search::isInfocomOption($_POST["itemtype"],$_POST["id_field"])) {

               $ic = new Infocom();
               $link_entity_type = -1;
               /// Specific entity item
               if ($searchopt[$_POST["id_field"]]["table"] == "glpi_suppliers") {
                  $ent = new Supplier();
                  if ($ent->getFromDB($_POST[$_POST["field"]])) {
                     $link_entity_type = $ent->fields["entities_id"];
                  }
               }
               foreach ($_POST["item"] as $key => $val) {
                  if ($val == 1) {
                     if ($item->getFromDB($key)) {
                        if ($link_entity_type < 0
                            || ($link_entity_type == $item->getEntityID())
                            || ($ent->fields["is_recursive"]
                                && in_array($link_entity_type, getAncestorsOf("glpi_entities",
                                            $item->getEntityID())))) {
                           $input2["items_id"] = $key;
                           $input2["itemtype"] = $_POST["itemtype"];

                           if ($ic->can(-1,'w',$input2)) {
                              // Add infocom if not exists
                              if (!$ic->getFromDBforDevice($_POST["itemtype"],$key)) {
                                 $input2["items_id"] = $key;
                                 $input2["itemtype"] = $_POST["itemtype"];
                                 unset($ic->fields);
                                 $ic->add($input2);
                                 $ic->getFromDBforDevice($_POST["itemtype"], $key);
                              }
                              $id = $ic->fields["id"];
                              unset($ic->fields);

                              if ($ic->update(array('id'            => $id,
                                                    $_POST["field"] => $_POST[$_POST["field"]]))) {
                                 $nbok++;
                              } else {
                                 $nbko++;
                              }
                           } else {
                              $nbnoright++;
                           }
                        } else {
                           $nbko++;
                        }
                     } else {
                        $nbko++;
                     }
                  }
               }

            } else { /// Not infocoms
               $link_entity_type = array();
               /// Specific entity item
               $itemtable = getTableForItemType($_POST["itemtype"]);

               $itemtype2 = getItemTypeForTable($searchopt[$_POST["id_field"]]["table"]);
               if ($item2 = getItemForItemtype($itemtype2)) {

                  if (($searchopt[$_POST["id_field"]]["table"] != $itemtable)
                      && $item2->isEntityAssign()
                      && $item->isEntityAssign()) {
                     if ($item2->getFromDB($_POST[$_POST["field"]])) {
                        if (isset($item2->fields["entities_id"])
                            && ($item2->fields["entities_id"] >= 0)) {

                           if (isset($item2->fields["is_recursive"])
                               && $item2->fields["is_recursive"]) {

                              $link_entity_type = getSonsOf("glpi_entities",
                                                            $item2->fields["entities_id"]);
                           } else {
                              $link_entity_type[] = $item2->fields["entities_id"];
                           }
                        }
                     }
                  }
               }
               foreach ($_POST["item"] as $key => $val) {
                  if ($val == 1) {
                     if ($item->can($key,'w')
                         && $item->canMassiveAction($_POST['action'], $_POST['field'],
                                                    $_POST[$_POST["field"]])) {
                        if ((count($link_entity_type) == 0)
                            || in_array($item->fields["entities_id"],$link_entity_type)) {
                           if ($item->update(array('id'            => $key,
                                                   $_POST["field"] => $_POST[$_POST["field"]]))) {
                              $nbok++;
                           } else {
                              $nbko++;
                           }
                        } else {
                           $nbko++;
                        }
                     } else {
                        $nbnoright++;
                     }
                  }
               }
            }
         }
         break;

      case "duplicate" : // For calendar duplicate in another entity
         if (method_exists($item,'duplicate')) {
            $options = array();
            if ($item->isEntityAssign()) {
               $options = array('entities_id' => $_POST['entities_id']);
            }
            foreach ($_POST["item"] as $key => $val) {
               if ($val == 1) {
                  if ($item->getFromDB($key)) {
                     if (!$item->isEntityAssign()
                         || ($_POST['entities_id'] != $item->getEntityID())) {
                        if ($item->can(-1,'w',$options)) {
                           if ($item->duplicate($options)) {
                              $nbok++;
                           } else {
                              $nbko++;
                           }
                        } else {
                           $nbnoright++;
                        }
                     } else {
                        $nbko++;
                     }
                  } else {
                     $nbko++;
                  }
               }
            }
         }
         break;

      case "install" :
         if (isset($_POST['softwareversions_id']) && ($_POST['softwareversions_id'] > 0)) {
            $inst = new Computer_SoftwareVersion();
            foreach ($_POST['item'] as $key => $val) {
               if ($val == 1) {
                  $input = array('computers_id'        => $key,
                                 'softwareversions_id' => $_POST['softwareversions_id']);
                  if ($inst->can(-1, 'w', $input)) {
                     if ($inst->add($input)) {
                        $nbok++;
                     } else {
                        $nbko++;
                     }
                  } else {
                     $nbnoright++;
                  }
               }
            }
         }
         break;

      case "add_group" :
         $groupuser = new Group_User();
         foreach ($_POST["item"] as $key => $val) {
            if ($val == 1) {
               $input = array('groups_id' => $_POST["groups_id"],
                              'users_id'  => $key);
               if ($groupuser->can(-1,'w',$input)) {
                  if ($groupuser->add($input)){
                     $nbok++;
                  } else {
                     $nbko++;
                  }
               } else {
                  $nbnoright++;
               }
            }
         }
         break;

      case "add_userprofile" :
         $right = new Profile_User();
         if (isset($_POST['profiles_id'])
             && ($_POST['profiles_id'] > 0)
             && isset($_POST['entities_id'])
             && ($_POST['entities_id'] >= 0)) {

            $input['entities_id']  = $_POST['entities_id'];
            $input['profiles_id']  = $_POST['profiles_id'];
            $input['is_recursive'] = $_POST['is_recursive'];
            foreach ($_POST["item"] as $key => $val) {
               if ($val == 1) {
                  $input['users_id'] = $key;
                  if ($right->can(-1,'w',$input)) {
                     if ($right->add($input)) {
                        $nbok++;
                     } else {
                        $nbko++;
                     }
                  } else {
                     $nbnoright++;
                  }
               }
            }
         }
         break;

      case "add_document" :
      case "add_document_item" :
         $documentitem = new Document_Item();
         foreach ($_POST["item"] as $key => $val) {
            if (isset($_POST['items_id'])) {
               // Add items to documents
               $input = array('itemtype'     => $_POST["itemtype"],
                              'items_id'     => $_POST["items_id"],
                              'documents_id' => $key);
            } else { // Add document to item
               $input = array('itemtype'     => $_POST["itemtype"],
                              'items_id'     => $key,
                              'documents_id' => $_POST['documents_id']);
            }
            if ($documentitem->can(-1, 'w', $input)) {
               if ($documentitem->add($input)) {
                  $nbok++;
               } else {
                  $nbko++;
               }
            } else {
               $nbnoright++;
            }
         }
         break;

      case "remove_document" :
      case "remove_document_item" :
         foreach ($_POST["item"] as $key => $val) {
            if (isset($_POST['items_id'])) {
               // Remove item to documents
               $input = array('itemtype'     => $_POST["itemtype"],
                              'items_id'     => $_POST["items_id"],
                              'documents_id' => $key);
            } else {
               // Remove contract to items
               $input = array('itemtype'     => $_POST["itemtype"],
                              'items_id'     => $key,
                              'documents_id' => $_POST['documents_id']);

            }
            $docitem = new Document_Item();
            if ($docitem->can(-1, 'w', $input)) {
               if ($item = getItemForItemtype($input["itemtype"])) {
                  if ($item->getFromDB($input['items_id'])) {
                     $doc = new Document();
                     if ($doc->getFromDB($input['documents_id'])) {
                        if ($docitem->getFromDBForItems($doc, $item)) {
                           if ($docitem->delete(array('id' => $docitem->getID()))) {
                              $nbok++;
                           } else {
                              $nbko++;
                           }
                        } else {
                           $nbko++;
                        }
                     } else {
                        $nbko++;
                     }
                  } else {
                     $nbko++;
                  }
               } else {
                  $nbko++;
               }
            } else {
               $nbnoright++;
            }
         }
         break;

      case "add_contact" :
         if ($_POST["itemtype"] == 'Supplier') {
            $contactsupplier = new Contact_Supplier();
            foreach ($_POST["item"] as $key => $val) {
               $input = array('suppliers_id' => $key,
                              'contacts_id'  => $_POST['contacts_id']);
               if ($contactsupplier->can(-1, 'w', $input)) {
                  if ($contactsupplier->add($input)) {
                     $nbok++;
                  } else {
                     $nbko++;
                  }
               } else {
                 $nbnoright++;
               }
            }
         }
         break;

      case "add_contract" :
      case "add_contract_item" :

         $contractitem = new Contract_Item();
         foreach ($_POST["item"] as $key => $val) {
            if (isset($_POST['items_id'])) {
               // Add items to contracts
               $input = array('itemtype'     => $_POST["itemtype"],
                              'items_id'     => $_POST["items_id"],
                              'contracts_id' => $key);
            } else { // Add contract to item
               $input = array('itemtype'     => $_POST["itemtype"],
                              'items_id'     => $key,
                              'contracts_id' => $_POST['contracts_id']);
            }
            if ($contractitem->can(-1, 'w', $input)) {
              if ($contractitem->add($input)) {
                  $nbok++;
               } else {
                  $nbko++;
               }
            } else {
               $nbnoright++;
            }
         }
         break;

      case "remove_contract" :
      case "remove_contract_item" :

         foreach ($_POST["item"] as $key => $val) {
            if (isset($_POST['items_id'])) {
               // Remove item to contracts
               $input = array('itemtype'     => $_POST["itemtype"],
                              'items_id'     => $_POST["items_id"],
                              'contracts_id' => $key);
            } else {
               // Remove contract to items
               $input = array('itemtype'     => $_POST["itemtype"],
                              'items_id'     => $key,
                              'contracts_id' => $_POST['contracts_id']);

            }
            $contractitem = new Contract_Item();
            if ($contractitem->can(-1, 'w', $input)) {
               if ($item = getItemForItemtype($input["itemtype"])) {
                  if ($item->getFromDB($input['items_id'])) {
                     $contract = new Contract();
                     if ($contract->getFromDB($input['contracts_id'])) {
                        if ($contractitem->getFromDBForItems($contract, $item)) {
                           if ($contractitem->delete(array('id' => $contractitem->getID()))) {
                              $nbok++;
                           } else {
                              $nbko++;
                           }
                        } else {
                           $nbko++;
                        }
                     } else {
                        $nbko++;
                     }
                  } else {
                     $nbko++;
                  }
               } else {
                  $nbko++;
               }
            } else {
               $nbnoright++;
            }
         }
         break;

      case "add_enterprise" :
         if ($_POST["itemtype"] == 'Contact') {
            $contactsupplier = new Contact_Supplier();
            foreach ($_POST["item"] as $key => $val) {
               $input = array('suppliers_id' => $_POST['suppliers_id'],
                              'contacts_id'  => $key);
               if ($contactsupplier->can(-1, 'w', $input)) {
                  if ($contactsupplier->add($input)) {
                     $nbok++;
                  } else {
                     $nbko++;
                  }
               } else {
                  $nbnoright++;
               }
            }
         }
         break;

      case "activate_infocoms" :
            $ic = new Infocom();
            if ($ic->canCreate()) {
               foreach ($_POST["item"] as $key => $val) {
                  $input = array('itemtype' => $_POST['itemtype'],
                                 'items_id' => $key);
                  if (!$ic->getFromDBforDevice($_POST['itemtype'], $key)) {
                      if ($ic->can(-1,'w',$input)) {
                        if ($ic->add($input)) {
                           $nbok++;
                        } else {
                           $nbko++;
                        }
                      } else {
                        $nbnoright++;
                      }
                  } else {
                     $nbko++;
                  }
               }
            }
         break;

      case "change_authtype" :
         foreach ($_POST["item"] as $key => $val) {
            if ($val == 1) {
               $ids[] = $key;
            }
         }
         if (Session::haveRight("user_authtype","w")) {
            if (User::changeAuthMethod($ids, $_POST["authtype"], $_POST["auths_id"])) {
               $nbok++;
            } else {
               $nbko++;
            }
         } else {
            $nbnoright++;
         }
         break;

      case "compute_software_category" :
         $softcatrule = new RuleSoftwareCategoryCollection();
         $soft = new Software();
         foreach ($_POST["item"] as $key => $val) {
            if ($val == 1) {
               $params = array();
               //Get software name and manufacturer
               if ($soft->can($key,'w')) {
                  $params["name"]             = $soft->fields["name"];
                  $params["manufacturers_id"] = $soft->fields["manufacturers_id"];
                  $params["comment"]          = $soft->fields["comment"];
                  //Process rules
                  if ($soft->update($softcatrule->processAllRules(null, $soft->fields, $params))) {
                     $nbok++;
                  } else {
                     $nbko++;
                  }
               } else {
                  $nbnoright++;
               }
            }
         }
         break;

      case "replay_dictionnary" :
         $softdictionnayrule = new RuleDictionnarySoftwareCollection();
         $ids                = array();
         foreach ($_POST["item"] as $key => $val) {
            if ($val == 1) {
               if ($item->can($key,'w')) {
                  $ids[] = $key;
               } else {
                  $nbnoright++;
               }
            }
         }
         if ($softdictionnayrule->replayRulesOnExistingDB(0, 0, $ids)>0){
            $nbok += count($ids);
         } else {
            $nbko += count($ids);
         }

         break;

      case "force_user_ldap_update" :
         Session::checkRight("user", "w");
         $user = new User();
         $ids = array();
         foreach ($_POST["item"] as $key => $val) {
            if ($val == 1) {
               $user->getFromDB($key);
               if (($user->fields["authtype"] == Auth::LDAP)
                   || ($user->fields["authtype"] == Auth::EXTERNAL)) {
                  if (AuthLdap::ldapImportUserByServerId(array('method' => AuthLDAP::IDENTIFIER_LOGIN,
                                                               'value'  => $user->fields["name"]),
                                                                1, $user->fields["auths_id"])) {
                     $nbok++;
                  } else {
                     $nbko++;
                  }
               }
            }
         }
         break;

      case "add_transfer_list" :
         if (!isset($_SESSION['glpitransfer_list'])) {
            $_SESSION['glpitransfer_list'] = array();
         }
         if (!isset($_SESSION['glpitransfer_list'][$_POST["itemtype"]])) {
            $_SESSION['glpitransfer_list'][$_POST["itemtype"]] = array();
         }
         foreach ($_POST["item"] as $key => $val) {
            if ($val == 1) {
               $_SESSION['glpitransfer_list'][$_POST["itemtype"]][$key] = $key;
            }
         }
         $REDIRECT = $CFG_GLPI['root_doc'].'/front/transfer.action.php';
         $nbok++;
         break;

      case "add_followup" :
         $fup = new TicketFollowup();
         foreach ($_POST["item"] as $key => $val) {
            if ($val == 1) {
               $input = array('tickets_id'      => $key,
                              'is_private'      => $_POST['is_private'],
                              'requesttypes_id' => $_POST['requesttypes_id'],
                              'content'         => $_POST['content']);
               if ($fup->can(-1,'w',$input)) {
                  if ($fup->add($input)) {
                     $nbok++;
                  } else {
                     $nbko++;
                  }
               } else {
                  $nbnoright++;
               }
            }
         }
         break;

      case "submit_validation" :
         $valid = new TicketValidation();
         foreach ($_POST["item"] as $key => $val) {
            if ($val == 1) {
               $input = array('tickets_id'         => $key,
                              'users_id_validate'  => $_POST['users_id_validate'],
                              'comment_submission' => $_POST['comment_submission']);
               if ($valid->can(-1,'w',$input)) {
                  if ($valid->add($input)) {
                     $nbok++;
                  } else {
                     $nbko++;
                  }
               } else {
                  $nbnoright++;
               }
            }
         }
         break;

      case "add_task" :
         if ($_POST['itemtype'] == 'Ticket') {
            $task  = new TicketTask();
            $field = 'tickets_id';
         } else if ($_POST['itemtype'] == 'Problem') {
            $task  = new ProblemTask();
            $field = 'problems_id';
         }
         foreach ($_POST["item"] as $key => $val) {
            if ($val == 1) {
               $input = array($field              => $key,
                              'taskcategories_id' => $_POST['taskcategories_id'],
                              'content'           => $_POST['content']);
               if ($task->can(-1,'w',$input)) {
                  if ($task->add($input)) {
                     $nbok++;
                  } else {
                     $nbko++;
                  }
               } else {
                  $nbnoright++;
               }
            }
         }
         break;

      case "add_actor" :
         $item = new $_POST['itemtype']();
         foreach ($_POST["item"] as $key => $val) {
            if ($val == 1) {
               $input = array('id' => $key);
               if (isset($_POST['_itil_requester'])) {
                  $input['_itil_requester'] = $_POST['_itil_requester'];
               }
               if (isset($_POST['_itil_observer'])) {
                  $input['_itil_observer'] = $_POST['_itil_observer'];
               }
               if (isset($_POST['_itil_assign'])) {
                  $input['_itil_assign'] = $_POST['_itil_assign'];
               }
               if ($item->can($key,'w')) {
                  if ($item->update($input)) {
                     $nbok++;
                  } else {
                     $nbko++;
                  }
               } else {
                  $nbnoright++;
               }
            }
         }
         break;

      case "link_ticket" :
         $ticket = new Ticket();
         if (isset($_POST['link']) && isset($_POST['tickets_id_1'])) {
            if ($ticket->getFromDB($_POST['tickets_id_1'])) {
               foreach ($_POST["item"] as $key => $val) {
                  if ($val == 1) {
                     $input['id']                    = $_POST['tickets_id_1'];
                     $input['_link']['tickets_id_1'] = $_POST['tickets_id_1'];
                     $input['_link']['link']         = $_POST['link'];
                     $input['_link']['tickets_id_2'] = $key;
                     if ($ticket->can($_POST['tickets_id_1'],'w')) {
                        if ($ticket->update($input)) {
                           $nbok++;
                        } else {
                           $nbko++;
                        }
                     } else {
                        $nbnoright++;
                     }
                  }
               }
            }
         }
         break;

      case 'reset' :
         if ($_POST["itemtype"] == 'CronTask') {
            Session::checkRight('config', 'w');
            $crontask = new CronTask();
            foreach ($_POST["item"] as $key => $val) {
               if (($val == 1)
                   && $crontask->getFromDB($key)) {
                  if ($crontask->resetDate()) {
                     $nbok++;
                  } else {
                     $nbko++;
                  }
               } else {
                  $nbko++;
               }
            }
         }
         break;

      case 'move_under' :
         if (isset($_POST['parent'])) {
            $fk = $item->getForeignKeyField();
            $parent = new $_POST["itemtype"]();
            if ($parent->getFromDB($_POST['parent'])) {
               foreach ($_POST["item"] as $key => $val) {
                  if (($val == 1)
                      && $item->can($key,'w')) {
                     // Check if parent is not a child of the original one
                     if (!in_array($parent->getID(), getSonsOf($item->getTable(),
                                   $item->getID()))) {
                        if ($item->update(array('id' => $key,
                                                $fk  => $_POST['parent']))) {
                           $nbok++;
                        } else {
                           $nbko++;
                        }
                     } else {
                        $nbko++;
                     }
                  } else {
                     $nbnoright++;
                  }
               }
            }
         }
         break;

      case 'merge' :
         $fk = $item->getForeignKeyField();
         foreach ($_POST["item"] as $key => $val) {
            if ($val == 1) {
               if ($item->can($key,'w')) {
                  if ($item->getEntityID() == $_SESSION['glpiactive_entity']) {
                     if ($item->update(array('id'           => $key,
                                             'is_recursive' => 1))) {
                        $nbok++;
                     } else {
                        $nbko++;
                     }
                  } else {
                     $input = $item->fields;

                     // Remove keys (and name, tree dropdown will use completename)
                     if ($item instanceof CommonTreeDropdown) {
                        unset($input['id'], $input['name'], $input[$fk]);
                     } else {
                        unset($input['id']);
                     }
                     // Change entity
                     $input['entities_id']  = $_SESSION['glpiactive_entity'];
                     $input['is_recursive'] = 1;
                     $input = Toolbox::addslashes_deep($input);
                     // Import new
                     if ($newid = $item->import($input)) {

                        // Delete old
                        if ($newid > 0) {
                           // delete with purge for dropdown with trash (Budget)
                           $item->delete(array('id'          => $key,
                                             '_replace_by' => $newid), 1);
                        }
                        $nbok++;
                     } else {
                        $nbko++;
                     }
                  }
               } else {
                  $nbnoright++;
               }
            }
         }
         break;

      case 'delete_email' :
      case 'import_email' :
         if (!$item->canCreate()) {
            $nbko++;
         } else {
            $emails_ids = array();
            foreach ($_POST["item"] as $key => $val) {
               if ($val == 1) {
                  $emails_ids[$key] = $key;
               }
            }
            if (!empty($emails_ids)) {
               $mailcollector = new MailCollector();
               if ($_POST["action"] == 'delete_email') {
                  $mailcollector->deleteOrImportSeveralEmails($emails_ids, 0);
               }
               else {
                  $mailcollector->deleteOrImportSeveralEmails($emails_ids, 1, $_POST['entities_id']);
               }
            }
            $nbok++;
         }
         break;

      default :
         // Plugin specific actions
         $split = explode('_',$_POST["action"]);
         $res   = '';
         if ($split[0] == 'plugin' && isset($split[1])) {
            // Normalized name plugin_name_action
            // Allow hook from any plugin on any (core or plugin) type
            $res = Plugin::doOneHook($split[1], 'MassiveActionsProcess', $_POST);

         } else if ($plug=isPluginItemType($_POST["itemtype"])) {
            // non-normalized name
            // hook from the plugin defining the type
            $res = Plugin::doOneHook($plug['plugin'], 'MassiveActionsProcess', $_POST);
         }

         if (is_array($res)
             && isset($res['ok'])
             && isset($res['ko'])
             && isset($res['noright'])) {
            $nbok      = $res['ok'];
            $nbko      = $res['ko'];
            $nbnoright = $res['noright'];
         } else {
            $nbok++;
         }
   }
   // Default message : all ok
   $message = __('Operation successful');
   // All failed. operations failed
   if ($nbok == 0) {
      $message = __('Failed operation');
      if ($nbnoright) {
         //TRANS: %$1d and %$2d are numbers
         $message .= "<br>".sprintf(__('(%1$d authorizations problems, %2$d failures)'),
                                     $nbnoright, $nbko);
      }
   } else if ($nbnoright || $nbko) {
      // Partial success
      $message = __('Operation performed partially successful');
      $message .= "<br>".sprintf(__('(%1$d authorizations problems, %2$d failures)'),
                                 $nbnoright, $nbko);
   }
   Session::addMessageAfterRedirect($message);
   Html::redirect($REDIRECT);

} else { //action, itemtype or item not defined
   echo "<div class='center'>".
         "<img src='".$CFG_GLPI["root_doc"]."/pics/warning.png' alt='warning'><br><br>";
   echo "<span class='b'>".__('No selected element or badly defined operation')."</span><br>";
   Html::displayBackLink();
   echo "</div>";
}

Html::footer();
?>