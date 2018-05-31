<?php

class JobListings {

    private $db; // DB Connection
    private $listingLimit = 10;
    private $pag;

    public function __construct() {
        $this->external_db_connect();
    }

    private function external_db_connect() {
        $this->db = new wpdb('DB_USER', 'DB_PASS', 'DB_NAME', 'DB_HOST');
    }

    public function get_job_listings() {
        $offset = 0;
        $limit = $this->listingLimit;

        // Check for query string, sanitise and set new offset
        if (isset($_GET['pag'])):
            $this->pag = preg_replace('/\D/', '', $_GET['pag']);

            // Extra check, ensure query string is numerical
            if (is_numeric($this->pag)):
                $offset = ($this->pag - 1) * $limit;
            endif;

        endif;

        // Modify query by search results
        if (isset($_POST)):
            $keywordSearch = stripslashes($_POST['keyword']);
            $locationSearch = stripslashes($_POST['location']);
            $skillSearch = stripslashes($_POST['skill']);
            $modQuery = "";

            // Keyword search
            if (!empty($keywordSearch)):
                $modQuery = " WHERE job_title LIKE '%$keywordSearch%'";
            endif;

            // Location search
            if (!empty($locationSearch)):
                $modQuery !== "" ? $modQuery .= " AND job_location = $locationSearch" : $modQuery .= " WHERE job_location = $locationSearch";
            endif;

            // Skill search
            if (!empty($skillSearch)):
                $jobRefs = $this->db->get_results("SELECT job_ref FROM jobskill_ol WHERE skill_ref = $skillSearch");
                $listIDString = "";
                foreach ($jobRefs as $k => $v):
                    foreach ($v as $w):
                        $listIDString .= $w . ",";
                    endforeach;
                endforeach;
                $listIDString = rtrim($listIDString, ",");
                $modQuery !== "" && !empty($listIDString) ? $modQuery .= " AND job_ref IN ($listIDString)" : $modQuery .= " WHERE job_ref IN ($listIDString)";
            endif;

        endif;

        // Search modifier variable, easier to check than $_POST values
        if (($_POST['keyword'] !== '' || $_POST['location'] !== '' || $_POST['skill'] !== '') && !empty($_POST)):
            $searched = true;
        endif;

        // DB query and results
        $listingsQuery = "SELECT * FROM jobs_ol";
        $searched ? $listingsQuery .= $modQuery : null;
        !$searched ? $listingsQuery .= " ORDER BY job_ref DESC LIMIT $limit OFFSET $offset" : null;
        $jobListings = $this->db->get_results($listingsQuery);

        // Empty check
        if ($jobListings):
            ?>
            <div class="listings" data-equalizer="listing-title">
                <?php
                foreach ($jobListings as $jobListing):
                    $ListingRef = $jobListing->job_ref;
                    $ListingName = $jobListing->job_title;
                    $listingLink = strtolower(preg_replace('~[\\\:*?"<>|().,]~', '', $ListingName)); // Strip special characters
                    $listingLink = str_replace(array(' ', '--', '/'), '-', $listingLink); // Strip spaces and double-dashes
                    $listingLink = trim(preg_replace("![^a-z0-9]+!i", "-", $listingLink), '-'); // Strip trailing dash
                    $jobLocation = $this->get_location($jobListing->job_location, true);
                    $jobSkill = $this->get_skill($jobListing->job_role, true);
                    $skillIcon = $this->get_icon();
                    ?>
                    <div id="listing-<?php echo $ListingRef; ?>" class="listing">

                        <div class="title-wrap" data-equalizer-watch="listing-title">
                            <img class="listing-icon" src="<?php echo $skillIcon['skill_icon']['url']; ?>" />
                            <h3><?php echo $ListingName; ?></h3>
                        </div>
                        <div class="details-wrap">

                            <div class="locations-wrap">
                                <span><?php echo $jobLocation; ?></span>
                            </div>
                            <div class="skills-wrap">
                                <span><?php echo$jobSkill; ?></span>
                            </div>
                            <div class="view-job">
                                <a href="<?php echo add_query_arg('ref', $ListingRef, get_bloginfo('url') . "/job-listing/" . $listingLink); ?>">View Job</a>
                                <i class="material-icons">keyboard_arrow_right</i>
                            </div>

                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="listings-no-results">
                <h3>No results found</h3>
                <p>Please use the form above to refine your search terms.</p>
            </div>
        <?php
        endif;
    }

    public function get_single_listing($listingID) {
        $singleQuery = "SELECT * FROM jobs WHERE job_ref = $listingID";
        $listingResults = $this->db->get_results($singleQuery);
        return $listingResults;
    }

    public function get_country($countryID) {
        $countryQuery = "SELECT * FROM country";
        $countryListings = $this->db->get_results($countryQuery);

        // Empty check
        if (!$countryListings): return;
        endif;

        foreach ($countryListings as $countryListing):
            $countryRef = $countryListing->country_ref;
            $countryName = $countryListing->country_name;

            // Match job list country ID to country list ref in DB
            if ($countryID == $countryRef):
                return $countryName;
            endif;

        endforeach;
    }

    public function get_location($locationID = false, $single = true) {
        $locationQuery = "SELECT * FROM location_ol ORDER BY description ASC";
        $locationListings = $this->db->get_results($locationQuery);

        // Empty check
        if (!$locationListings): return;
        endif;

        if ($single && $locationID):

            foreach ($locationListings as $locationListing):
                $locationRef = $locationListing->loc_ref;
                $locationName = $locationListing->description;

                // Match job list country ID to country list ref in DB
                if ($locationID == $locationRef):
                    return $locationName;
                endif;

            endforeach;

        elseif (!$single):
            return $locationListings;
        endif;
    }

    public function get_skill($skillID = false, $single = false) {
        $skillQuery = "SELECT * FROM skill_ol ORDER BY description ASC";
        $skillListings = $this->db->get_results($skillQuery);

        // Empty check
        if (!$skillListings): return;
        endif;

        if ($single && $skillID):

            foreach ($skillListings as $skillListing):
                $skillRef = $skillListing->skill_ref;
                $skillName = $skillListing->description;

                // Match job list country ID to country list ref in DB
                if ($skillID == $skillRef):
                    return $skillName;
                endif;

            endforeach;

        elseif (!$single):
            return $skillListings;
        endif;
    }

    public function get_icon() {
        // Randomly select and return an icon
        $skillIcons = get_field('listing_skill_icons', 'option');
        $randIcon = array_rand($skillIcons, 1);
        $icon = $skillIcons[$randIcon];
        return $icon;
    }

    public function listings_pagination() {
        $countQuery = "SELECT COUNT(job_ref) FROM jobs_ol";
        $listingsCount = $this->db->get_results($countQuery);
        $limit = $this->listingLimit;
        $pagLink = '';

        // Retrieve count from the returned object
        foreach ($listingsCount[0] as $k => $v):
            $count = $v;
        endforeach;

        // Bail if the total count is less than the limit to show
        if ($limit > $count): return;
        endif;

        if (($_POST['keyword'] !== '' || $_POST['location'] !== '' || $_POST['skill'] !== '') && !empty($_POST)): return;
        endif;

        // Round up for pagination
        $paginationCount = ceil($count / $limit);
        ?>
        <div id="listing-pagination">
            <?php
            for ($i = 1; $i <= $paginationCount; $i++):

                // Check for active pag
                if ($i == $this->pag):
                    $pagLink = '';
                else:
                    $pagLink = "href='" . add_query_arg('pag', $i, get_bloginfo('url') . "/available-jobs") . "'";
                endif;
                ?>
                <a class="listing-pag <?php echo $i == $this->pag ? "active-pag" : null; ?>" <?php echo $pagLink; ?>><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
        <?php
    }

    public function get_latest_jobs() {
        $latestQuery = "SELECT * FROM jobs ORDER BY ref ASC LIMIT 3";
        $latestListings = $this->db->get_results($latestQuery);
        ?>
        <div class="recent-listings-container">
            <?php
            foreach ($latestListings as $latestListing):
                $listingRef = $latestListing->job_ref;
                $listingName = $latestListing->job_title;
                $listingLink = strtolower(preg_replace('~[\\\:*?"<>|().,]~', '', $listingName)); // Strip special characters
                $listingLink = str_replace(array(' ', '--', '/'), '-', $listingLink); // Strip spaces and double-dashes
                $listingLink = trim(preg_replace("![^a-z0-9]+!i", "-", $listingLink), '-'); // Strip trailing dash
                $jobLocation = $this->get_location($latestListing->job_location, true);
                $jobSkill = $this->get_skill($latestListing->job_role, true);
                ?>
                <div class="recent-listing">
                    <div class="recent-title">
                        <a href="<?php echo add_query_arg('ref', $listingRef, get_bloginfo('url') . "/jobs/" . $listingLink); ?>">
                            <h3><?php echo $listingName; ?></h3>
                        </a> 
                    </div>

                    <?php if (!empty($jobSkill)): ?>
                        <div class="recent-skills">
                            <span>
                                <small class="listing-label">Skill:</small>
                                <?php echo $jobSkill; ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($jobLocation)): ?>
                        <div class="recent-locations">
                            <span>
                                <small class="listing-label">Location:</small>
                                <?php echo $jobLocation; ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <div class="button-wrap">
                        <a href="<?php echo add_query_arg('ref', $listingRef, get_bloginfo('url') . "/jobs/" . $listingLink); ?>">
                            Apply Now
                            <i class="material-icons">keyboard_arrow_right</i>
                        </a>
                        <span class="button-border border-topleft"></span>
                        <span class="button-border border-topright"></span>
                        <span class="button-border border-bottomleft"></span>
                        <span class="button-border border-bottomright"></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

}
