I needed a super fast way to bookmark postition while driving or cycling in order to simplify Openstreetmap mapping.
Simply tap a button and FE auto-captures two positions (second is for speed and direction) and sends them to BE. Data is saved to sqlite db.
There's no authentication and currently as it's AI written, there are clear bugs in it. But the basics work.


LLM-generated description: mobile-friendly web app for collecting labeled GPS points in the field. Users tap category buttons 
(BENCH, BIN, TREE, TABLE) to record two GPS readings 2 seconds apart, compute great-circle distance, speed (km/h), 
and numeric direction, and batch-send multiple captures at once. The frontend runs entirely in the browser using
the Geolocation API with warm-up and accuracy filtering, while a minimal PHP backend stores all records in a single
SQLite table and provides a simple HTML endpoint to view the latest entries. Designed for fast deployment, offline-tolerant
field use, and easy inspection without external dependencies.

PS. I don't think it's really offline-tolerant, looks like if upload fails, collected data might get deleted.

Known bugs todos:
* When whole webpage won't fit into view, then page becomes scrollable and tapping on button may accidentally scroll page instead
* Offline resiliency need testing
* Support for icons for buttons
* ~~Calculated columns dt, speed, distance, direction are shown 0.0 in data view~~ - Was GPS bug
* Locking phone screen loses gps fix, unless user has other gps running at same time
* ⚠️⚠️⚠️ Browser can't force system  to reload gps position, only way to make page usable is to have GPS tracking app open in background
* ~~Map to see notes~~
  * Due to using 💎 emoji as direction marker, Android 7 and earlier will render direction 45deg to right.
* ~~Needs ability to mark notes as resolved~~
