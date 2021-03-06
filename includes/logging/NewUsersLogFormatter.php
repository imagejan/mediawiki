<?php
/**
 * Formatter for new user log entries.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @author Niklas Laxström
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 * @since 1.22
 */

/**
 * This class formats new user log entries.
 *
 * @since 1.19
 */
class NewUsersLogFormatter extends LogFormatter {
	protected function getMessageParameters() {
		$params = parent::getMessageParameters();
		$subtype = $this->entry->getSubtype();
		if ( $subtype === 'create2' || $subtype === 'byemail' ) {
			$userName = $this->entry->getTarget()->getText();
			if ( isset( $params[3] ) ) {
				$target = User::newFromId( $params[3] );
				if ( $target->getId() != 0 ) {
					$userName = $target->getName();
				}
			} else {
				$target = User::newFromName( $userName, false );
			}
			global $wgUser;
			if ( $target->getId() == 0 ) {
				$userName = $this->entry->getTarget()->getText() . ' (deleted)';
				$params[2] = Message::rawParam( htmlentities( $userName ) );
			} elseif ( !$wgUser->isAllowed( 'block' ) ) {
				$params[2] = Message::rawParam( $this->makeUserLink( $target ) );
			} else {
				$s = $this->makeUserLink( $target ) . ' (';
				$authenticated = $target->getEmailAuthenticationTimestamp();
				if ( $authenticated ) {
					global $wgLang;
					$s .= 'authenticated on ' . $wgLang->timeanddate( $authenticated );
				} else {
					$s .= 'not yet authenticated' . $this->getMergeSpamLink( $userName );
				}
				$s .= ')';
				$params[2] = Message::rawParam( $s );
			}
			$params[3] = $userName;
		}

		return $params;
	}

	protected function getMergeSpamLink( $userName ) {
		global $wgUser;
		if ( $userName == 'Spam' || !$wgUser->isAllowed( 'usermerge' ) ) {
			return "";
		}
		global $wgServer, $wgArticlePath;
		return '; <a href="' . $wgServer .
			preg_replace( '/\$1/', 'Special:UserMerge', $wgArticlePath ) .
			'?olduser=' . htmlentities( $userName ) . '&newuser=Spam&deleteuser=true">Merge with <i>Spam</i> account</a>';
	}

	public function getComment() {
		$timestamp = wfTimestamp( TS_MW, $this->entry->getTimestamp() );
		if ( $timestamp < '20080129000000' ) {
			# Suppress $comment from old entries (before 2008-01-29),
			# not needed and can contain incorrect links
			return '';
		}

		global $wgUser;
		if ( $wgUser->isAllowed( 'block' ) && $this->entry->getSubtype() === 'create' ) {
			$userName = $this->entry->getTarget()->getText();
			$target = User::newFromId( $userName );
			if ( $target->getId() == 0 ) {
				return $userName . ' (deleted)' . parent::getComment();
			} else {
				$authenticated = $target->getEmailAuthenticationTimestamp();
				if ( !$authenticated ) {
					return parent::getComment() . $this->getMergeSpamLink( $userName );
				}
			}
		}
		return parent::getComment();
	}

	public function getPreloadTitles() {
		$subtype = $this->entry->getSubtype();
		if ( $subtype === 'create2' || $subtype === 'byemail' ) {
			// add the user talk to LinkBatch for the userLink
			return [ Title::makeTitle( NS_USER_TALK, $this->entry->getTarget()->getText() ) ];
		}

		return [];
	}
}
