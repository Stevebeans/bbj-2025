<?php
//Insert ads after second paragraph of single post content.

//add_filter("the_content", "prefix_insert_post_ads");

function prefix_insert_post_ads($content)
{
  if (!premiumCheck() && (is_single() || is_page()) && get_post_type() !== "live-feed-updates") {
    $ad_codes = [file_get_contents(locate_template("template-parts/ads/ad-in-article.php")), file_get_contents(locate_template("template-parts/ads/taboola-mid.php")), file_get_contents(locate_template("template-parts/ads/ad-in-article.php")), file_get_contents(locate_template("template-parts/ads/ad-in-article.php"))];
    $paragraphs = explode("</p>", $content);
    $count = 0;
    foreach ($paragraphs as $index => $paragraph) {
      if (trim($paragraph)) {
        $paragraphs[$index] .= "</p>";
        $count++;
        if (in_array($count, [1, 4, 9, 15]) && !empty($ad_codes)) {
          $paragraphs[$index] .= array_shift($ad_codes);
        }
      }
    }
    $content = implode("", $paragraphs);
  }
  return $content;
}

// add_filter("the_content", "prefix_insert_post_ads");

// function prefix_insert_post_ads($content)
// {
//   if (!premiumCheck()):
//     $first_ad_code = get_template_part("template-parts/ads/ad-in-article");
//   endif;

//   if (!premiumCheck()):
//     $second_ad_code = get_template_part("template-parts/ads/taboola-mid");
//     echo "HEY";
//   endif;

//   $third_ad_code = $first_ad_code;
//   $fourth_ad_code = $first_ad_code;

//   if (is_single() && "live-feed-updates" != get_post_type()) {
//     $content = prefix_insert_after_paragraph($first_ad_code, 1, $content);
//     $content = prefix_insert_after_paragraph($second_ad_code, 3, $content);
//     $content = prefix_insert_after_paragraph($third_ad_code, 7, $content);
//     $content = prefix_insert_after_paragraph($fourth_ad_code, 11, $content);
//     return $content;
//   }
//   return $content;
// }

// // Parent Function that makes the magic happen

// function prefix_insert_after_paragraph($insertion, $paragraph_id, $content)
// {
//   $closing_p = "</p>";
//   $paragraphs = explode($closing_p, $content);
//   foreach ($paragraphs as $index => $paragraph) {
//     if (trim($paragraph)) {
//       $paragraphs[$index] .= $closing_p;
//     }

//     if ($paragraph_id == $index + 1) {
//       $paragraphs[$index] .= $insertion;
//     }
//   }

//   return implode("", $paragraphs);
// }
