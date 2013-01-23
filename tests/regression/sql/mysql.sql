/* mysql has a few extras which we should be wary of */
# http://dev.mysql.com/doc/refman/5.1/en/language-structure.html
-- kate doesn't get this right

-- these should all be valid strings

SELECT 'abcdefgh';
SELECT "abcdefgh";
CREATE TABLE `xyz`;
SELECT 'an apost''rophe'
SELECT 'an apost\'rophe';

