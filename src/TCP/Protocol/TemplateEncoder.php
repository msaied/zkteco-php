<?php

declare(strict_types=1);

namespace ZkTeco\TCP\Protocol;

use ZkTeco\Values\Template;
use ZkTeco\Values\User;

/**
 * Encodes a {@see User} together with their {@see Template}s into the buffer
 * that CMD_SAVE_USERTEMPS (the high-rate save path) expects.
 *
 * The buffer is a 12-byte head — the byte lengths of the user block, the finger
 * table, and the template block — followed by those three blocks in order:
 *
 *   head:     <III>  len(user) len(table) len(templates)
 *   user:     one 73-byte record (see {@see encodeUser()})
 *   table:    per template <bHbI>  tag(2) uid fingerIndex+0x10 offset
 *   templates: per template <H + raw>  len(blob) then the opaque blob
 *
 * Layout, the 0x02 tag and the 0x10 finger-index base are ported from pyzk's
 * HR_save_usertemplates / Finger.repack_only.
 */
final class TemplateEncoder
{
    /**
     * pyzk's `fnum`: table finger indexes are stored offset by this base.
     */
    private const FINGER_INDEX_BASE = 0x10;

    /**
     * @param  list<Template>  $templates
     */
    public static function encode(User $user, array $templates, string $encoding = NameField::DEFAULT_ENCODING): string
    {
        $userBlock = self::encodeUser($user, $encoding);
        $table = '';
        $templateBlock = '';
        $offset = 0;

        foreach ($templates as $template) {
            $packed = pack('v', strlen($template->data)).$template->data;

            $table .= pack('c', 2)
                .pack('v', $user->uid)
                .pack('c', self::FINGER_INDEX_BASE + $template->fingerIndex)
                .pack('V', $offset);

            $offset += strlen($packed);
            $templateBlock .= $packed;
        }

        $head = pack('V', strlen($userBlock))
            .pack('V', strlen($table))
            .pack('V', strlen($templateBlock));

        return $head.$userBlock.$table.$templateBlock;
    }

    /**
     * The 73-byte user record the save-templates buffer carries (pyzk's
     * repack73): the same fields as the 72-byte CMD_USER_WRQ record, but
     * prefixed with a 0x02 tag and with a 0x01 flag byte after the card number.
     */
    private static function encodeUser(User $user, string $encoding): string
    {
        $password = $user->password ?? '';
        $card = $user->cardNumber !== null ? (int) $user->cardNumber : 0;

        return pack('C', 2)
            .pack('v', $user->uid)
            .pack('C', $user->privilege->value)
            .str_pad(substr($password, 0, 8), 8, "\0")
            .NameField::pack($user->name, 24, $encoding)
            .pack('V', $card)
            .pack('C', 1)
            .str_pad(substr((string) $user->groupId, 0, 7), 7, "\0")
            ."\0"
            .str_pad(substr($user->userId, 0, 24), 24, "\0");
    }
}
