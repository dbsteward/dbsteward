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


An example of feature development:

1. In a new sprint, feature/PROJ-123-new-feature-gps-tracking is branched from master for feature development
2. The file db/users.xml is edited to add the column user_location to the user table as
```XML
  <column name="user_location" type="geography('POINT')"/>
```
3. After testing and review, feature/PROJ-123-new-feature-gps-tracking is brought to master as a pull request
4. When v2.4.0 is branched from master, it contains the sum of all v2.4.0 changes and when compared to v2.3.4, it's database changes include the new column user_location
```SQL
ALTER TABLE user
  ADD COLUMN user_location geography('POINT');
```

For details on differencing two versions of an application, see [Using DBSteward](https://github.com/nkiraly/DBSteward/blob/master/doc/USING.md)
