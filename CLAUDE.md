# Big Brother Junkies - Project Documentation

## Project Overview
WordPress-based community platform for Big Brother fandom featuring custom post types, REST APIs, and member features.

## Architechure Goals 
- Moving toward auto loading php files rather than require_once this means using namespaces
- Ensure everything possible is object cached. I will write something that busts the cache every time a post or feed update is saved
- This is a high performance site so I am looking for minimal loading per page and ideally code that can imrpove my google page speed insights
- It is a community driven site so anything you can think of that can enhance the community feeling would be helpful 


## Tech Stack
- **CMS**: WordPress with custom theme (BBJ) and plugin (bbj-v2)
- **Frontend**: Tailwind CSS 3.4, Flowbite UI, vanilla JS + jQuery
- **Backend**: PHP (WordPress standards), custom MySQL tables
- **Build**: wp-scripts, Tailwind CLI, npm workspaces (monorepo)
- **APIs**: REST API with JWT auth, WP GraphQL, Google Maps API

## Color Palette (from tailwind.config.js)
```
primary500: "#35546e"    - Main blue
primarySoft: "#4D6D88"   - Softer blue
primaryHard: "#2D4B65"   - Darker blue
second500: "#FFBF0F"     - Main yellow
secondSoft: "#ffd970"    - Softer yellow
secondHard: "#FA910A"    - Orange accent
thirdColor: "#E55C41"    - Red accent
```

## Font Families
- **sans**: Roboto (body)
- **osw**: Oswald (headings)
- **mainHead**: Yanone Kaffeesatz (primary headings)
- **hand**: Caveat (cursive accents)

## Directory Structure
```
/bbj (WordPress root)
├── wp-content/
│   ├── themes/BBJ/           # Custom theme
│   ├── plugins/
│   │   ├── bbj-v2/           # Core functionality plugin
│   │   ├── bbj-tools/        # Additional tools
│   │   └── bbj-next/         # Next.js integration
├── package.json              # Monorepo config
└── CLAUDE.md                 # This file
```

## Custom Post Types

### bigbrother-players
- Player profiles with social links, photos, occupation
- Custom table: `wp_bbj_players`
- REST + GraphQL enabled

### bigbrother-seasons
- Season info with dates, winner, runner-up, AFP
- Custom table: `wp_bbj_seasons`
- REST + GraphQL enabled

### live-feed-updates
- Real-time updates during episodes
- GraphQL: feedUpdate / feedUpdates

### player-season-link
- Junction table for player-season relationships
- Custom table: `wp_bbj_v2_player_season`
- Stores stats: HOH, POV, nominations, etc.

## Key Constants (bbj-v2 plugin)
```php
BBJ_V2_PATH, BBJ_V2_URL, BBJ_V2_INCLUDES
BBJ_V2_TABLE_PLAYERS = "wp_bbj_players"
BBJ_V2_TABLE_SEASONS = "wp_bbj_seasons"
BBJ_V2_TABLE_LINKS   = "wp_bbj_v2_player_season"
```

## REST API Endpoints
- `POST /wp-json/bbj/v1/feed-update` - Create feed update (requires updater role)
- `GET /wp-json/bbj/v1/search?query=` - Search players, seasons, posts

## Important Theme Files
- `functions.php` - Theme setup, assets, constants
- `tailwind.config.js` - Colors, fonts, Tailwind config
- `header.php` - Navigation, user role detection
- `includes/MB/` - Meta Box field definitions

## Important Plugin Files (bbj-v2)
- `index.php` - Bootstrap, constants, image sizes
- `includes/PostTypes/` - CPT definitions
- `includes/Routes/` - REST API endpoints
- `includes/Public/shortcodes/` - Shortcode implementations
- `includes/Helpers/global-functions.php` - Cache functions

## Caching Strategy
- Cache group: `bbj_v2`
- TTL: 300 seconds
- Key functions: `bbj_spoiler_bar_cache_key()`, `bbj_players_cache_key()`
- Bust with: `bbj_spoiler_bar_bust_cache($season_id)`

## Build Commands
```bash
npm start              # Watch mode (all workspaces)
npm run build          # Production build
npm run tailwindwatch  # Tailwind watch only
npm run preview        # BrowserSync + watch
```

## Current Season
Stored in option: `get_option('bbj_v2_current_season')`

## Coding Standards
- Follow WordPress PHP coding standards
- Use existing cache patterns for new queries
- CPTs use custom tables (not post meta) for relational data
- REST endpoints require permission callbacks

## Key Integrations
- **Meta Box**: Custom fields with custom table storage
- **Breeze**: Cache clearing on content updates
- **AIOSEO**: Schema filtering
- **MemberPress**: Membership/paywall
- **Formidable**: Forms with Stripe/PayPal
- **JWT Auth**: REST API authentication

## Image Sizes
- `bbj_v2_profile_image`: 375x375
- `bbj_v2_index_hero`: 1350x450
- `bbj_v2_index_mobile`: 400x333
- `player-banner`: 1200x350
- `featured-thumbnail`: 400x200
