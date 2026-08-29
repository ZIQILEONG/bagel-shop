<?php
// Included by user-listing.php for both normal and AJAX renders.
// Expects $pager, $arr, $search, $sort, $dir already set.
$total_count = $pager->item_count ?? count($arr);
?>

<div style="background: #ffffff; border: 1px solid #ebdcd5; border-radius: 18px; padding: 22px; box-shadow: 0 4px 20px rgba(62, 38, 25, 0.04);">
    <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 16px; border-bottom: 1px solid #f5ebe4; margin-bottom: 16px;">
        <span style="font-size: 13.5px; color: #968377; font-weight: 600;">
            Showing <b><?= count($arr) ?></b> of <b><?= $total_count ?></b> active member(s)
        </span>
        <button type="button" 
                onclick="confirmBulkDelete()" 
                style="background: #ffffff; color: #c0392b; border: 1.5px solid #f8cfcf; padding: 7px 16px; border-radius: 8px; font-size: 12.5px; font-weight: 700; cursor: pointer; transition: all 0.2s ease;">
            🗑️ Deactivate Selected
        </button>
    </div>

    <form method="post" id="bulkDeleteForm" action="user-listing.php">
        <input type="hidden" name="btn" value="delete_selected">

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="background: #faf5f0;">
                        <th style="width: 40px; text-align: center; padding: 12px 10px; border-top-left-radius: 8px; border-bottom-left-radius: 8px;">
                            <input type="checkbox" id="selectAllUsers" style="width: 16px; height: 16px; accent-color: #cf7953; cursor: pointer;">
                        </th>
                        <th style="width: 60px; padding: 12px 8px; font-size: 12px; font-weight: 800; text-transform: uppercase; color: #968377;">Photo</th>
                        <th style="padding: 12px 10px; font-size: 12px; font-weight: 800; text-transform: uppercase; color: #968377;">
                            <a href="#" onclick="setSort('id'); return false;" style="color: inherit; text-decoration: none;">
                                ID <?= $sort === 'id' ? ($dir === 'asc' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th style="padding: 12px 10px; font-size: 12px; font-weight: 800; text-transform: uppercase; color: #968377;">
                            <a href="#" onclick="setSort('name'); return false;" style="color: inherit; text-decoration: none;">
                                Name <?= $sort === 'name' ? ($dir === 'asc' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th style="padding: 12px 10px; font-size: 12px; font-weight: 800; text-transform: uppercase; color: #968377;">
                            <a href="#" onclick="setSort('email'); return false;" style="color: inherit; text-decoration: none;">
                                Email <?= $sort === 'email' ? ($dir === 'asc' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th style="padding: 12px 10px; font-size: 12px; font-weight: 800; text-transform: uppercase; color: #968377;">
                            <a href="#" onclick="setSort('role'); return false;" style="color: inherit; text-decoration: none;">
                                Role <?= $sort === 'role' ? ($dir === 'asc' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th style="padding: 12px 10px; font-size: 12px; font-weight: 800; text-transform: uppercase; color: #968377;">Phone No</th>
                        <th style="text-align: center; padding: 12px 10px; font-size: 12px; font-weight: 800; text-transform: uppercase; color: #968377; border-top-right-radius: 8px; border-bottom-right-radius: 8px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($arr)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px 20px; color: #968377; font-size: 14px;">
                                🔍 No users found matching your search.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($arr as $u): ?>
                            <?php 
                            $photo_file = !empty($u->photo) ? htmlspecialchars($u->photo) : 'default.jpg';
                            $is_current_user = ($u->id == $_user->id);
                            ?>
                            <tr style="border-bottom: 1px solid #f7eeea; transition: background 0.15s ease;" onmouseover="this.style.background='#fdfaf7'" onmouseout="this.style.background='transparent'">
                                <td style="text-align: center; padding: 14px 10px;">
                                    <?php if (!$is_current_user): ?>
                                        <input type="checkbox" name="ids[]" value="<?= htmlspecialchars($u->id) ?>" class="user-cb" style="width: 16px; height: 16px; accent-color: #cf7953; cursor: pointer;">
                                    <?php else: ?>
                                        <span title="You cannot select yourself" style="font-size: 12px; color: #968377;">🔒</span>
                                    <?php endif; ?>
                                </td>

                                <td style="padding: 10px 8px;">
                                    <div style="width: 44px; height: 44px; border-radius: 10px; overflow: hidden; border: 1.5px solid #ebdcd5; background: #faf6f0;">
                                        <img src="/photos/<?= $photo_file ?>" 
                                             alt="<?= htmlspecialchars($u->name) ?>" 
                                             style="width: 100%; height: 100%; object-fit: cover; display: block;"
                                             onerror="this.onerror=null; this.src='../photos/<?= $photo_file ?>'; this.onerror=function(){this.src='/photos/default.jpg';};">
                                    </div>
                                </td>

                                <td style="padding: 14px 10px; font-weight: 700; color: #968377; font-size: 13px;">
                                    #<?= htmlspecialchars($u->id) ?>
                                </td>

                                <td style="padding: 14px 10px; font-weight: 700; color: #3e2619; font-size: 14px;">
                                    <a href="user-detail.php?id=<?= htmlspecialchars($u->id) ?>" style="color: #3e2619; text-decoration: none;">
                                        <?= htmlspecialchars($u->name) ?>
                                    </a>
                                    <?php if ($is_current_user): ?>
                                        <span style="font-size: 11px; color: #cf7953; font-weight: 800; margin-left: 4px;">(You)</span>
                                    <?php endif; ?>
                                </td>

                                <td style="padding: 14px 10px; font-size: 13.5px;">
                                    <a href="mailto:<?= htmlspecialchars($u->email) ?>" style="color: #4a3b32; text-decoration: none;">
                                        <?= htmlspecialchars($u->email) ?>
                                    </a>
                                </td>

                                <td style="padding: 14px 10px;">
                                    <?php if ($u->role === 'Admin'): ?>
                                        <span style="display: inline-block; background: #eaf3ff; color: #1d68cd; border: 1px solid #c8e0ff; font-size: 11.5px; font-weight: 800; padding: 3px 10px; border-radius: 999px;">
                                            Admin
                                        </span>
                                    <?php else: ?>
                                        <span style="display: inline-block; background: #eaf6ed; color: #217d47; border: 1px solid #c6e9d0; font-size: 11.5px; font-weight: 800; padding: 3px 10px; border-radius: 999px;">
                                            Member
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td style="padding: 14px 10px; font-size: 13px; color: #6b584d;">
                                    <?= !empty($u->phone_no) ? htmlspecialchars($u->phone_no) : '<span style="color:#b8a89f;">—</span>' ?>
                                </td>

                                <td style="text-align: center; padding: 14px 10px;">
                                    <a href="user-detail.php?id=<?= htmlspecialchars($u->id) ?>" 
                                       style="display: inline-block; background: #cf7953; color: #ffffff; padding: 6px 14px; border-radius: 8px; font-size: 12.5px; font-weight: 700; text-decoration: none; box-shadow: 0 2px 6px rgba(207,121,83,0.25);">
                                        Detail
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>

    <?php if ($pager && $pager->page_count > 1): ?>
        <div style="margin-top: 22px; display: flex; justify-content: center;">
            <div class="pager" style="display: flex; gap: 6px;">
                <?= $pager->html('search=' . encode($search) . '&sort=' . $sort . '&dir=' . $dir) ?>
            </div>
        </div>
    <?php endif; ?>
</div>