# Staff Performance Point Calculation

## Final point rules

Performance score ka existing base `100` rahega. Neeche diye gaye points us base score me add ya minus honge.

| Activity | Point calculation |
|---|---:|
| Follow-up admin se approved | `+2` |
| Follow-up admin se "No point" ke saath approved | `0` |
| Follow-up admin se disapproved | `-2` |
| Follow-up due time cross hote hi | `-2` |
| Follow-up 24 hours se zyada late | Har agle started 24-hour period par aur `-2` |
| Normal assigned task time par complete | `+2` |
| Normal assigned task due time cross hote hi | `-2` |
| Normal assigned task 24 hours se zyada late | Har agle started 24-hour period par aur `-2` |
| Order entry | `+0.25` |
| Issuement | `+0.25` |
| Return | `+0.25` |
| Receiving | `+0.25` |
| Purchase | `+0.25` |

Late calculation ka exact example:

- Due time tak complete: late penalty `0`
- Due time se 1 second late se lekar exactly 24 hours late tak: `-2`
- 24 hours aur 1 second late se lekar exactly 48 hours late tak: total `-4`
- 48 hours aur 1 second late hone par: total `-6`

Approval point aur late penalty alag rules hain. Isliye late follow-up approve hone par approval ka `+2` aur applicable late penalty dono lagenge. "No point" approval me approval bonus `0` rahega, lekin follow-up late tha to late penalty phir bhi lagegi.

## Completion rating points

Completed aur received order par `orders.assign` permission wala admin Order Details screen se **Rate Order** ya **Update Rating** use kar sakta hai. Rating 1 se 5 stars tak hogi aur existing formula retain kiya gaya hai:

`Rating points = (stars - 3) × 5`

| Rating | Points |
|---|---:|
| 1 star | `-10` |
| 2 stars | `-5` |
| 3 stars | `0` |
| 4 stars | `+5` |
| 5 stars | `+10` |

Rating assigned order follower ke performance me rating save hone wale month me count hogi. Admin ka remark audit ke liye order ke saath save rahega.

## Duplicate scoring protection

- Standalone task aur aisa linked assigned work jiska alag business point rule nahi hai, normal task ke `+2/-2 per started late day` rule se ek baar score hoga.
- Order follow-up aur gold issuement request ki mirror task entry normal task points dobara add/minus nahi karegi, kyunki inka approval/issuement score alag se lagta hai.
- Linked workflow ko sirf ek applicable rule se score kiya jayega. Example: gold issuement complete hone par `+0.25`; uski linked mobile task ke alag `+2` points nahi milenge. Diamond-bag preparation jiska alag listed business point nahi hai, normal task rule se score hoga.
- Order follow-up schedule aur uski mobile task ek hi work hain; scoring follow-up rule se hogi, mirror task se nahi.
- Duplicate follow-up text aur photo/evidence performance points ko add ya minus nahi karte. Admin approval decision, lateness aur listed business activities hi follow-up/business points ko affect karte hain.

## Late points aur cron

Pending late task/follow-up ka point dashboard aur mobile performance API khulte waqt current time se calculate hota hai. Is point calculation ko badhane ke liye alag score cron required nahi hai. Existing notification cron reminders/push notifications ke liye alag responsibility rakhta hai.

## Correction summary

- Follow-up approval ko `+2`, no-point approval ko `0`, aur disapproval ko `-2` kiya gaya.
- Late penalty ko due time cross hote hi `-2`, phir har additional started 24-hour period par `-2` kiya gaya.
- Order, issuement, return, receiving aur purchase ko `+0.25` each kiya gaya.
- Duplicate/photo based performance points hata diye gaye.
- Rating ka existing 5-point-per-star-from-3 formula retain kiya gaya.
- Completed received order par admin rating button, rating update aur remark flow retain aur verify kiya gaya.
- Normal task aur separately scored linked workflow ki calculation alag ki gayi, jisse duplicate points nahi aayenge aur baaki assigned tasks normal `+2/-2` rule me rahenge.
- Point calculation ke liye koi SQL report/import file required nahi hai; live application service hi calculation karegi.
