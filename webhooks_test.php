<?php
/* Copyright (C) 2022	Open-Dsi          <support@open-dsi.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 * or see http://www.gnu.org/
 */

print '<br>_SERVER :<br>' . json_encode($_SERVER);
print '<br>_REQUEST :<br>' . json_encode($_REQUEST);
print '<br>_POST :<br>' . json_encode($_POST);
print '<br>_GET :<br>' . json_encode($_GET);
print '<br>HTTP_RAW_POST_DATA :<br>' . json_encode($HTTP_RAW_POST_DATA);
$postdata = file_get_contents("php://input");
print '<br>postdata :<br>' . json_encode($postdata);
print '<br>_FILES :<br>' . json_encode($_FILES);

http_response_code(200);
