# Development Lifecycle Best Practices

An important aspect of using DBSteward is understanding how to maximize it's effectiveness for development cycles and release eningeering.

Application development should not be hindered but streamlined by using DBSteward as your primary database change management system.

The recommended way to do this is by managing your database as you do you code in a given branch. That means:
- master branch is stable, complete, and represents CURRENT development
- topic branches only contain changes that pertain to the topic the branch was made for
- release branches and tags are stable and complete and do not contain previous or next version definition details
- dot-releases (2.3.0) are for new features, and are the only place database changes happen
- dot-dot-releases (2.3.4) are for code-level changes and bugfixes for existing features


# Software Development Lifecycle Examples and Recommendations

Suppose your product is developed and managed in branches and tags like this:

1. master - all features, improvements, and non-critical bugs branch from master
2. feature/PROJ-123-new-feature-gps-tracking - features are developed, tested, and reviewed as topic branches with a clear naming convention of type/ticket#-dash-summary
3. bugfix/PROJ-125-slow-page-loads - non-critical bugs treated as scheduled work are topical bugfix/ branches, branched from master
4. v2.3.4 tag - dot-dot releases are tagged as v2.3.4 release version and not modified. a tag is a moment-in-time release
5. hotfix/PROJ-127-broken-charts - critical bugs are hotfixes and are triaged and dealt with ASAP, branched from a release tag or branch
6. 2.3.4 branch - the 2.3.4 branch may be made to create a branch lineage as part of hotfix review and reintegration
7. 2.3.5 branch and v2.3.5 tag - the 2.3.5 branch is created from the 2.3.4 branch and then any bugfix or hot fix tickets are branched from this branch for pull request reintegration. When all fix tickets have been reintegrated into 2.3.5 branch, it is tagged as v2.3.5 and released.


## Feature development example

1. The new feature ticket PROJ-123 for user gps tracking is planned and vetted
2. In support of the ticket, feature/PROJ-123-new-feature-gps-tracking is branched from master for code and database feature development
3. The file db/users.xml is edited to add the column user_location to the user table as
```XML
  <column name="user_location" type="geography('POINT')"/>
```
4. After testing and review, feature/PROJ-123-new-feature-gps-tracking is brought to master as a pull request
5. When v2.4.0 is branched from master, it contains the sum of all v2.4.0 changes and when compared to v2.3.4, it's database changes include the new column user_location
```SQL
ALTER TABLE user
  ADD COLUMN user_location geography('POINT');
```

For details on differencing two versions of an application, see [Using DBSteward](https://github.com/dbsteward/dbsteward/blob/master/doc/USING.md)


## Bugfix example

1. It is determined that user_location must not be NULL in this geospatial centerice application, ticketed as PROJ-128
2. In support of the ticket, bugfix/PROJ-128-user-location-required is branched from master for code and database refinement and scheduled to be included in the 2.5.0 release
3. The file db/users.xml is edited to specify the table user column user_location must not be null
```XML
  <column name="user_location" type="geography('POINT')" null="false"/>
```
4. After testing and review, branch bugfix/PROJ-128-user-location-required is brought to master as a pull request
5. When v2.5.0 is branched from master and compared to v2.4.0, the database structure changes include the new constraints for user_location
```SQL
ALTER TABLE user
  ALTER COLUMN user_location SET NOT NULL;
```

