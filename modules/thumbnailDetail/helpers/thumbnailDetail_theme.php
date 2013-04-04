<?php defined("SYSPATH") or die("No direct script access.");
/**
 * Gallery - a web based photo album viewer and editor
 * Copyright (C) 2000-2010 Bharat Mediratta
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or (at
 * your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street - Fifth Floor, Boston, MA  02110-1301, USA.
 */
class thumbnailDetail_theme_Core {

  static function thumb_info($theme, $item) {
    $results = "";

    //if album, show number of photos, if photo, show tags
    if ($item->is_album())
    {
        $results = "Photos: " . (string)html::clean($item->viewable()->descendants_count());
    }
    else if ($item->is_photo())
    {
        $tags = array();
        foreach (tag::item_tags($item) as $tag)
        {
            $tags[] = $tag->name;
        }
        if ($tags)  //if no tags, hide "Tags: " 
        {
            $results = "Tags: " . implode(",", $tags); 
        }
        else
        {
            $results = "";
        }
    }
    else
    {
        $results = "";
    }

  return $results;
  }
}
