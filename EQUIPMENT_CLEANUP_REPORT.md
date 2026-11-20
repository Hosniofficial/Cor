# تقرير مراجعة تنظيف المعدات والمواد

**تاريخ المراجعة:** 2026-11-19

**النطاق:** كتالوج المعدات والمواد الفعّال، بيانات الخادم، ومصادر المكافآت والأسعار الموجودة في المشروع. الأعداد والقوائم أدناه لقطة المراجعة في 2026-11-19.

## تحديث التنفيذ — 2026-11-20

- أُوقفت مكافآت الموارد غير المعرّفة `food_e`, `wood_e`, `stone_e`, `metal_e`, `coin_e` من حزم المبتدئين للمستويات 8–10، دون بدائل، حسب توجيه المستخدم.
- أُعيد شرط `gold_bar_piece` بكمية 20 إلى وصفة `Item.gold_bar` مؤقتًا لحين اعتماد قرار نهائي بشأن مصادر القطعة.
- لم تُحذف تعريفات معدات أو مواد، ولم تُعدّل سجلات قاعدة بيانات أو صور. قائمة المرشحين أدناه لا تزال تحتاج مراجعة استخدام/مخزون قبل الحذف الجماعي.
- لا يتوفر تصدير حديث للمخزون؛ لم تُجرَ أي عملية على حساب الاختبار أو قاعدة بيانات.

## الملخص التنفيذي

| المجال | ما وُجد | التوصية |
|---|---|---|
| المعدات | 330 تعريفًا، متطابقة المفاتيح مع بيانات الخادم | لا تحذف تعريفًا قبل فحص مخزون الإنتاج والسجلات |
| مصادر المعدات | 100 تعريف بلا مصدر منح حالي؛ منها 15 قطعة بداية مطلوبة في وصفات | راجعها كمقترحات، لا كقائمة حذف مؤكدة |
| صور المعدات | 126 سجلًا تشير إلى 86 صورة غير موجودة؛ 41 منها قابلة لإسقاط المعارك | استعد الصور أو أصلح المراجع قبل حذف تعريفات قابلة للمنح |
| المواد | 400 مادة في قائمة اللغة الفعّالة، و402 معرفًا في ملف الأسعار | أصلح التباينات بين اللغة والأسعار والبيانات الأساسية |
| مصادر المواد | 191 مادة لا يظهر لها مصدر منح مفحوص أو سعر ذهب موجب | مرشحات مراجعة فقط؛ بعض المعرفات قد تكون قديمة أو خارجية |
| صور المواد | 141 تعريفًا تشير إلى 138 صورة غير موجودة؛ 11 منها لها مسار منح أو شراء مفحوص | ابدأ بإصلاح صور المواد الـ11 القابلة للاكتساب |
| قاعدة الإنتاج | لم تُفحص | لا تنفذ حذفًا جماعيًا قبل استعلام مخزون اللاعبين والتقارير |

---

# أولًا: المعدات

## البيانات والإصدار

الإصدار النشط مضبوط في `caesar-api/controllers/PlayerController.php` على `JsVersion = -0.0.24`. مفاتيح كتالوج المعدات النشط `caesar-assets/api/js-0.0.24/json/equipment/ar.json` تطابق مفاتيح `caesar-private/data/equipment-power.json` على الخادم: **330 مقابل 330**، بلا مفاتيح زائدة أو ناقصة.

نسخ `ar.json` الموجودة في مجلدَي API والويب للإصدار `3.4.113` مطابقة أيضًا للكتالوج النشط في هذه المقارنة. لا يعني ذلك أن كل ملف إصدار آخر آمن للحذف؛ قد تظل هناك روابط أو عملاء بإصدار مختلف.

## تركيبات الإسقاط غير المتاحة

مصدر الخام يحدد نسب الإسقاط والأطقم، ويختار قطعة من `sword`, `shield`, `helmet`, `armor`, `boot`, `belt`. لكن الحزام غير معرّف في الأطقم:

`sparta`, `lion`, `mili`, `peace`, `mars`, `fearless`, `thunder`, `neptune`, `thor`

كما أن مستويات القطع الأساسية في معظم الأطقم متوفرة عند المستوى 1 فقط. لذلك يبقى السلوك الصحيح هو الإبقاء على نسب الإسقاط ومجموعات الأطقم من الخام، مع اختيار `(الطقم، القطعة، المستوى)` الموجودة في بيانات الإصدار النشط فقط. عند غياب مستوى الهدف، يُستخدم أعلى مستوى معرّف لا يتجاوزه.

## صور المعدات المفقودة

**126 تعريفًا** تشير إلى **86 مسار صورة فريدًا** غير موجود تحت `caesar-assets/web/images/equip`. تشمل هذه الملفات:

| الأطقم | مسارات الصور المشار إليها وغير الموجودة |
|---|---|
| dark | `equip30.jpg`–`equip39.jpg` |
| saton | `equip60.jpg`–`equip64.jpg` |
| holy | `equip75.jpg`–`equip79.jpg` |
| sparta | `equip90.jpg`–`equip94.jpg` |
| volcan | `equip105.jpg`–`equip109.jpg` |
| mili | `equip121.jpg` |
| fart | `equip135.jpg`–`equip139.jpg` |
| peace | `equip150.jpg`–`equip154.jpg` |
| victory | `equip165.jpg`–`equip169.jpg` |
| mars | `equip180.jpg`–`equip184.jpg` |
| khaled | `equip210.jpg`–`equip214.jpg` |
| blue | `equip225.jpg`–`equip229.jpg` |
| worrior | `equip240.jpg`–`equip244.jpg` |
| thunder | `equip255.jpg`–`equip259.jpg` |
| neptune | `equip270.jpg`–`equip274.jpg` |
| thor | `equip285.jpg`–`equip289.jpg` |
| light | `equip300.jpg`–`equip304.jpg` |

**41 تعريفًا** من مجموعة الصور هذه يمكن أن تنتجها إسقاطات المعارك الحالية. استعادة الصور أو تصحيح مراجعها أولى من حذف تعريفات هذه المكافآت. كما أن صورة الاحتياط `images/equip/equip228.jpg` المستخدمة عند غياب تعريف المعدات غير موجودة.

## تعريفات المعدات التي لا يظهر لها مصدر منح حالي

مقارنة المصادر الحالية وجدت **100 تعريف** بلا مصدر منح في جداول الجوائز أو المهام أو المساهمة أو التبادل أو إسقاط المعارك:

| الفئة | العدد | التعريفات |
|---|---:|---|
| طقم `dark` | 50 | القطع الأساسية مستوى 1، والقطع الثانوية `belt/necklace/pendant/ring/steed` للمستويات 1–9 |
| الأطقم `blue`, `fart`, `light`, `saton`, `volcan`, `worrior` | 30 | لكل طقم `sword/helmet/shield/armor/boot` مستوى 1 |
| القطع الأساسية لطقم `khaled` | 5 | `sword/helmet/shield/armor/boot` مستوى 1 |
| قطع البداية المطلوبة للترقية فقط | 15 | `belt/necklace/pendant/ring/steed` مستوى 1 للأطقم `loy`, `victory`, `khaled` |

آخر 15 قطعة تظهر كمتطلبات وصفات مساهمة، لكن لم يظهر لها مصدر بداية. حذفها دون معالجة الوصفات قد يجعل سلاسل الترقية غير قابلة للبدء.

### خطوات تنظيف المعدات

1. افحص مخزون الإنتاج قبل الحذف، بما في ذلك القطع المركبة على الأبطال:

   ```sql
   SELECT type, part, lvl, COUNT(*) AS inventory_count
   FROM player_equip
   GROUP BY type, part, lvl
   ORDER BY type, part, lvl;
   ```

2. افحص تقارير المعارك القديمة أو الاستيرادات السابقة؛ عدم وجود مصدر منح حالي لا يثبت أن القطعة لم تُمنح تاريخيًا.
3. استعد صور المعدات المفقودة أو صحح مراجعها، وابدأ بالقطع التي يمكن إسقاطها حاليًا.
4. وفّر قطع البداية الـ15 المستخدمة في وصفات الترقية، أو راجع تلك الوصفات.
5. بعد التحقق من المخزون والإصدارات المدعومة، احذف فقط التعريفات التي تأكد عدم الحاجة إليها من كل نسخ الكتالوج وبيانات الخادم معًا.

---

# ثانيًا: المواد (Items)

## الكتالوج الفعلي وتناسق الإصدارات

- قائمة اللغة التي يحمّلها العميل من `ItemLang/ar.json` هي **400 مادة** في الإصدار الفعّال `-0.0.24`.
- ملف `itemBase.json` يحوي **269 مادة** فقط: توجد **133 مادة** في قائمة اللغة ولا توجد فيه، ومادتان في `itemBase` فقط هما `hammer` و`snowcity`.
- العميل يحمّل `ItemBase` في رد AJAX، لكن منطق تهيئة المواد يستخدم قائمة `ItemLang` ولا يدمج محتوى `ItemBase` فيها. لذلك لا يكفي وجود مادة في `itemBase` لإثبات أنها قابلة للعرض في قائمة المواد الفعّالة.
- نسخ `itemBase.json` الثلاث متطابقة. أما `ItemLang/ar.json` للإصدار النشط والنسخة `3.4.113` فلكل منهما 400 مفتاح، لكن **297 تعريفًا تختلف قيمها**؛ لا تنسخ أو توحّد ملفات اللغة بين الإصدارين تلقائيًا.

## الأسعار ومصادر المنح التي تمت مراجعتها

| المصدر | معرفات فريدة مذكورة كمكافآت أو منح |
|---|---:|
| جوائز العالم `Win` | 122 |
| `Lose` | 6 |
| `Sp` | 73 |
| `Plunder` | 38 |
| مكافآت المهام | 30 |
| مكافآت المساهمة | 2 |
| مكافآت التبادل | 23 |
| هدايا بدء الحساب | 20 |
| عجلة الحظ | 20 |
| محتويات صناديق الخشب/الذهب/الهدايا وحزم المبتدئين | 50 |
| اتحاد معرفات المنح في هذه المصادر | 186 |

ملف الأسعار `caesar-private/data/item-prices.json` يحتوي **402 معرفًا فريدًا**: منها **157** بسعر ذهب موجب و**245** بسعر صفر. الصفر لا يعني أن المادة غير مستخدمة؛ قد تكون مكافأة أو متطلبًا أو مادة غير قابلة للشراء حاليًا.

بعد جمع مصادر المنح المذكورة والأسعار الموجبة، توجد **209 مادة** من قائمة اللغة لها مسار منح أو شراء ظاهر في هذه الملفات، وتبقى **191 مادة** لا يظهر لها أحد هذين المسارين في المصادر المفحوصة. هذه **قائمة مراجعة وليست قائمة حذف**؛ لا تشمل أنظمة توزيع خارجية أو تاريخ قاعدة البيانات أو جميع الأحداث القديمة.

## تباينات يجب معالجتها قبل التنظيف

1. كانت حزم المبتدئين من المستوى 8 فما فوق تمنح **خمسة معرفات** غير موجودة في `ItemLang` أو `itemBase` أو ملف الأسعار:
   - `food_e`
   - `wood_e`
   - `stone_e`
   - `metal_e`
   - `coin_e`

   أُزيلت هذه المعرفات من مكافآت الحزم دون بديل؛ لم تكن لها تعريفات أو أيقونات أو مسارات استخدام فعّالة.

2. `hammer` موجود في `itemBase` وسعره **50 ذهبًا**، لكنه غير موجود في قائمة اللغة النشطة. وجود السعر وحده لا يضمن ظهور اسمه وبياناته للعميل.
3. `snowcity` موجود في `itemBase` وله سجل سعر صفر و`use: none`، لكنه غير موجود في قائمة اللغة النشطة. يحتاج قرارًا واضحًا: دعم فعّال وإضافة بيانات اللغة، أو مرشح حذف بعد فحص الإنتاج.
4. لم يظهر لـ`gold_bar_piece` مصدر منح أو سعر ذهب موجب في ملفات الجوائز التي فُحصت. مع ذلك، ورد أنه يُكتسب فعليًا من القلاع، ولم يُحسم مصدر هذه المكافأة في بيانات المشروع الحالية. أُعيد شرط 20 قطعة في وصفة `Item.gold_bar` مؤقتًا إلى حين مراجعة مصدر القلاع واعتماد قرار نهائي.

## تعريفات بلا مصدر منح أو سعر موجب

الأعداد التالية هي أعداد معرفات حسب بادئة الاسم داخل قائمة المواد الـ191 المرشحة، وليست حكمًا بأن المجموعة مهجورة:

| البادئة | العدد | البادئة | العدد | البادئة | العدد |
|---|---:|---|---:|---|---:|
| بدون بادئة مركبة | 20 | `letter` | 25 | `offer` | 43 |
| `army` | 13 | `gold` | 7 | `festival` | 6 |
| `arena` | 4 | `peace` | 4 | `sol` | 4 |
| `thor` | 3 | `luna` | 3 | `mars` | 3 |
| `senate` | 3 | `silver` | 3 | `bronze` | 2 |
| `chat` | 2 | `christmas` | 2 | `demigod` | 2 |
| `easter` | 2 | `exp` | 2 | `king` | 2 |
| `spartans` | 2 | `tagned` | 2 | `ancient` | 1 |
| `apollo` | 1 | `city` | 1 | `corn` | 1 |
| `diamond` | 1 | `expan` | 1 | `freedom` | 1 |
| `god` | 1 | `golden` | 1 | `help` | 1 |
| `hephaestus` | 2 | `hero` | 1 | `lost` | 1 |
| `luxury` | 1 | `medal` | 1 | `miraculous` | 1 |
| `motiv` | 1 | `old` | 1 | `platinum` | 1 |
| `production` | 1 | `retreat` | 1 | `roma` | 1 |
| `shining` | 1 | `soul` | 1 | `staduim` | 3 |
| `union` | 1 | `vertumnus` | 1 | `win` | 1 |
| `winter` | 1 |  |  |  |  |

من أبرز المجموعات التي تحتاج مراجعة مصدرها: العناصر الداخلية `offer_*` وعددها 43، رسائل/تذاكر ترقيات العتاد `letter_*` وعددها 25، وعناصر الحزم والأحداث. قد تكون بعض هذه المفاتيح سجلات عرض أو محتوى قديمًا، ولا تمثل مادة مخزّنة في مخزون اللاعب.

<details>
<summary>عرض قائمة معرفات المواد الـ191 التي لم يظهر لها مسار منح أو سعر ذهب موجب</summary>

```text
MinervaGem, MinervaHolyWater, MinervaHolyWater.L, MinervaHolyWater.M, MinervaHolyWater.S, MinervaHolyWaterPack, RebelMail, RebelMessenger, TrialCertificate, debris, doughnut, drachma, essence, ghostemblem, huizhang, king, medalofcraft, pumpkinlantem, snowman, spartans
ancient_coins, apollo_pass, arena_attempt_10, arena_attempt_5, arena_exp_1, arena_exp_5
army_a_1000, army_all_pack, army_b_100, army_b_1000, army_build_ex, army_c_100, army_c_1000, army_d_100, army_d_1000, army_e_100, army_e_1000, army_f_100, army_f_1000
bronze_bar_pack, bronze_cup, chat_horn, chat_horn_pack, christmas_stocking, christmas_tree, city_take_away, corn_seed, demigod_papirus, demigod_power, diamond_supply_crate, easter_box, easter_pack, exp_arena_100, exp_arena_1000, expan_plan_pack, festival_gray, festival_green, festival_orange, festival_purple, festival_special, festival_yellow, freedom_take_away, god_spring, gold_1, gold_1000, gold_5, gold_500, gold_bar_pack, gold_bar_piece, gold_cup, golden_supply_crate, help_house_slog, hephaestus_pieces_a, hephaestus_pieces_b, hero_power_pack, king_chest, king_slyer
letter_belt_1, letter_belt_2, letter_belt_3, letter_belt_up_2, letter_belt_up_3, letter_necklace_1, letter_necklace_2, letter_necklace_3, letter_necklace_up_2, letter_necklace_up_3, letter_pendant_1, letter_pendant_2, letter_pendant_3, letter_pendant_up_2, letter_pendant_up_3, letter_ring_1, letter_ring_2, letter_ring_3, letter_ring_up_2, letter_ring_up_3, letter_steed_1, letter_steed_2, letter_steed_3, letter_steed_up_2, letter_steed_up_3
lost_pack, luna_fragment, luna_pergament, luna_stone, luxury_pack, mars_pieces_a, mars_pieces_b, mars_pieces_c, medal_pack, miraculous_coins, motiv_pack
offer_MinervaHolyWater_1, offer_MinervaHolyWater_2, offer_archit_1, offer_archit_2, offer_archit_3, offer_archit_4, offer_building_speed, offer_draco, offer_expan_plan, offer_expan_plan_2, offer_flags, offer_gold_medal, offer_hammer, offer_hero_exp, offer_jop_1, offer_jop_2, offer_jop_3, offer_jop_4, offer_king_medal, offer_luck_1, offer_luck_2, offer_lux_1, offer_lux_2, offer_lux_3, offer_lux_4, offer_lux_5, offer_lux_6, offer_lux_7, offer_marcus_scheme, offer_motiv, offer_peace, offer_production_pack, offer_production_pack_2, offer_pronze_bar_i, offer_pronze_bar_ii, offer_silver_bar_1, offer_silver_bar_2, offer_silver_bar_3, offer_silver_bar_4, offer_silver_bar_5, offer_silver_bar_6, offer_truce, offer_war
old_gitar, peace_emblem, peace_emblem_pack, peace_flag, peace_medal, platinum_supply_crate, production_pack, retreat_point_pack, roma_colosseum_box, senate_golden_box, senate_platinum_box, senate_silver_box, shining_coin, silver_bar_pack, silver_cup, silver_supply_crate, sol_chest, sol_fragment, sol_pergament, sol_stone, soul_of_mars, spartans_flag_pack, spartans_pack, staduim_blue, staduim_orange, staduim_red, tagned_pack, tagned_random, thor_fragment, thor_pergament, thor_stone, union_pack, vertumnus_box, win_glass, winter_pack
```

</details>

## صور المواد المفقودة

فحص جميع مسارات الصور في كتالوج اللغة النشط وجد **141 مادة** تشير إلى **138 مسارًا فريدًا** غير موجود تحت `caesar-assets/web`. من هذه المواد، **11** لها مصدر منح أو سعر موجب في المصادر التي تمت مراجعتها؛ أصلح هذه أولًا:

| المادة | الصورة المشار إليها | المسار الظاهر |
|---|---|---|
| `sell_voucher` | `SecretLetter.jpg` | مكافأة مهمة، وسعر موجب |
| `owl_of_minerva` | `OwlofMinerva.jpg` | جائزة عالم |
| `spartans_relics` | `spartansRelics.jpg` | جائزة عالم، وسعر موجب |
| `rose_flower` | `RoseFlower.jpg` | سعر موجب |
| `food_pack` | `SaturnPack.jpg` | سعر موجب |
| `beginner_back_7` إلى `beginner_back_10` | `item059.jpg` إلى `item062.jpg` | محتويات حزم المبتدئين |
| `necklace_4_pack` | `Trans.black.pocket.jpg` | سعر موجب |
| `gold_10` | `item080.jpg` | عجلة الحظ ومحتويات الصناديق |

الـ130 مادة الأخرى ذات المسارات المفقودة لا يظهر لها مسار منح أو سعر ذهب موجب في المصادر الحالية. يلزم حسم ما إذا كانت تاريخية أو داخلية أو مدعومة عبر نظام آخر قبل حذف تعريفاتها أو صورها.

<details>
<summary>عرض معرفات المواد الأخرى ذات الصور المفقودة (130 مادة)</summary>

```text
help_house_slog, union_pack, expan_plan_pack, freedom_take_away, city_take_away, king_slyer, army_build_ex, medal_pack, retreat_point_pack, RebelMessenger, RebelMail, TrialCertificate, ancient_coins, apollo_pass, drachma, miraculous_coins, gold_cup, bronze_cup, silver_cup, christmas_stocking, christmas_tree, corn_seed, spartans, spartans_pack, spartans_flag_pack, mars_pieces_c, bronze_bar_pack, silver_bar_pack, gold_bar_pack, ghostemblem, pumpkinlantem, huizhang, peace_emblem_pack, peace_emblem, staduim_orange, staduim_red, staduim_blue, king, snowman, old_gitar, win_glass, chat_horn, chat_horn_pack, doughnut, hero_power_pack, exp_arena_100, exp_arena_1000, production_pack, easter_pack, easter_box, army_all_pack, tagned_pack, tagned_random, MinervaGem, MinervaHolyWater, MinervaHolyWater.S, MinervaHolyWater.M, MinervaHolyWater.L, MinervaHolyWaterPack, king_chest, roma_colosseum_box, senate_golden_box, senate_platinum_box, senate_silver_box, platinum_supply_crate, diamond_supply_crate, silver_supply_crate, vertumnus_box, winter_pack, lost_pack, letter_pendant_up_2, letter_pendant_up_3, letter_belt_up_2, letter_belt_up_3, letter_necklace_up_2, letter_necklace_up_3, letter_ring_up_2, letter_ring_up_3, letter_steed_up_2, letter_steed_up_3, letter_pendant_1, letter_pendant_2, letter_pendant_3, letter_belt_1, letter_belt_2, letter_belt_3, letter_necklace_1, letter_necklace_2, letter_necklace_3, letter_ring_1, letter_ring_2, letter_ring_3, letter_steed_1, letter_steed_2, letter_steed_3, luxury_pack, gold_1, gold_5, gold_500, gold_1000, offer_pronze_bar_i, offer_pronze_bar_ii, offer_flags, offer_gold_medal, offer_lux_1, offer_lux_2, offer_lux_3, offer_lux_4, offer_lux_5, offer_lux_6, offer_lux_7, offer_king_medal, offer_MinervaHolyWater_1, offer_MinervaHolyWater_2, offer_peace, offer_silver_bar_2, offer_silver_bar_3, offer_silver_bar_4, offer_silver_bar_5, offer_production_pack, offer_production_pack_2, offer_luck_1, offer_jop_1, offer_jop_2, offer_jop_3, offer_jop_4, offer_building_speed, offer_marcus_scheme, offer_expan_plan_2, offer_war
```

</details>

## خطوات تنظيف المواد

1. افحص سجلات مخزون الإنتاج لكل معرف مادة، ثم قارن النتائج بقائمة الـ191 مرشحًا. استعلام القراءة الأولي:

   ```sql
   SELECT id_item, COUNT(*) AS player_rows, SUM(amount) AS total_amount
   FROM player_item
   GROUP BY id_item
   ORDER BY id_item;
   ```

2. افحص أرشيف تقارير المعارك والمهام، وسجلات الشراء أو التوزيع الخارجي قبل حذف أي معرف.
3. أُزيلت مكافآت الموارد `*_e` غير المدعومة. ما يزال `hammer` قابلًا للشراء بسعر موجب دون تعريف لغة في الإصدار النشط؛ عالج ترجمته ومظهره قبل إتاحته للاعبين.
4. أصلح صور المواد الـ11 ذات مسارات المنح أو الشراء الحالية، أو صحح المراجع إلى صور موجودة.
5. أُعيد `gold_bar_piece` إلى متطلبات وصفة السبيكة الذهبية بالكمية الأصلية (20) مؤقتًا، لحين اعتماد القرار النهائي بشأن اكتسابه من القلاع.
6. بعد إثبات أن المعرف غير مستخدم في المخزون والإصدارات المدعومة ومصادر التوزيع الخارجية، احذف سجله من كل كتالوج وسعر ومصدر مكافأة ذي صلة. لا تحذف الصورة إذا كانت مشتركة مع مادة أخرى.

## حدود التقرير

لم يتم الاتصال بقاعدة الإنتاج أو فحص `player_item` أو `player_equip` فيها. كما لا يغطي فحص المصادر توزيعات أو عروضًا خارج الملفات الحالية أو سجلات تاريخية. لذلك فإن كل قائمة «بلا مصدر» هي قائمة تحقق للمراجعة، وليست تصريحًا آمنًا بالحذف.
