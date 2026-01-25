I needed a super fast way to bookmark postition while driving or cycling in order to simplify Openstreetmap mapping.
Simply tap a button and FE auto-captures two positions (second is for speed and direction) and sends them to BE. Data is saved to qlite db.
There's no authentication and currently as it's AI written, there are clear bugs in it. But the basics work.


LLM-generated description: mobile-friendly web app for collecting labeled GPS points in the field. Users tap category buttons 
(BENCH, BIN, TREE, TABLE) to record two GPS readings 2 seconds apart, compute great-circle distance, speed (km/h), 
and numeric direction, and batch-send multiple captures at once. The frontend runs entirely in the browser using
the Geolocation API with warm-up and accuracy filtering, while a minimal PHP backend stores all records in a single
SQLite table and provides a simple HTML endpoint to view the latest entries. Designed for fast deployment, offline-tolerant
field use, and easy inspection without external dependencies.

PS. I don't think it's really offline-tolerant, looks like if upload fails, collected data might get deleted.
