<div id="feed_subscribe"><a href="<?php echo url_for("/feed/"); ?>">订阅TIPI的更新吧!</a></div>
<ul>
    <?php
    foreach ($chapt_list as $chapt) {
        $is_current_page = ($current_page_name == $chapt['page_name']);
        echo "<li class='" . ($is_current_page ? "current_page" : "") . "'>";
        echo "<a href='" . url_for_book($chapt['page_name']) . "'>{$chapt['title']}</a>";
        if (isset($chapt['list']) && ! empty($chapt['list'])) {
            echo "<ul>";
            foreach ($chapt['list'] as $sub_list) {
                $is_current_page = ($current_page_name == $sub_list['page_name']);
                echo "<li class='" . ($is_current_page ? "current_page" : "" ) . "'>";
                echo "<a href='" . url_for_book($sub_list['page_name']) . "'>{$sub_list['title']}</a>";
                if (isset($sub_list['list']) && ! empty($sub_list['list'])) {
                    echo "<ul>";
                    foreach ($sub_list['list'] as $row) {
                        $is_current_page = ($current_page_name == $row['page_name']);
                        echo "<li class='" . ($is_current_page ? "current_page" : "" ) . "'>";
        				echo "<a href='" . url_for_book($row['page_name']) . "'>{$row['title']}</a>";
                        echo "</li>";
                    }
                    echo "</ul>";
                }
                echo "</li>";
            }
            echo "</ul>";
        }
        echo "</li>";
    }
    ?>
</ul>

