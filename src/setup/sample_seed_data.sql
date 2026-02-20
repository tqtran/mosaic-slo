DROP TABLE IF EXISTS `slo_import`;

CREATE TABLE `slo_import` (
  `Academic Year` LONGTEXT,
  `Term` LONGTEXT,
  `Semester` LONGTEXT,
  `Course` LONGTEXT,
  `CRN` LONGTEXT,
  `CSLO` LONGTEXT,
  `Met/Not Met` LONGTEXT,
  `StudentID` LONGTEXT,
  `Course Status` LONGTEXT,
  `Title` LONGTEXT,
  `Modality` LONGTEXT,
  `SLO Language` LONGTEXT,
  `Program` LONGTEXT,
  `Sub Code` LONGTEXT,
  `Subject` LONGTEXT,
  `Assessment` LONGTEXT
) ENGINE=InnoDB;

START TRANSACTION;
SET foreign_key_checks=0;
SET unique_checks=0;

INSERT INTO `slo_import` (`Academic Year`,`Term`,`Semester`,`Course`,`CRN`,`CSLO`,`Met/Not Met`,`StudentID`,`Course Status`,`Title`,`Modality`,`SLO Language`,`Program`,`Sub Code`,`Subject`,`Assessment`) VALUES
('2021-22','Fall 2021','Fall','JAPN C185','24640','CSLO 1','Met','C00154354','Active','Elementary Japanese 2','Online','Recognize and produce the Japanese language at the advanced beginning level in the four primary areas of communication: listening, speaking, reading, and writing. 	 Describe and analyze significant differences in culture-specific behaviors between the cul','International Languages','JAPN','Japanese','Assignment'),
('2021-22','Fall 2021','Fall','JAPN C185','24640','CSLO 2','Met','C00154354','Active','Elementary Japanese 2','Online','Describe and analyze significant differences in culture-specific behaviors between the cultures of the Japanese-speaking world and the United States by identifying the culture in which the variant is practiced (personal space, non-verbal behavior, treatm','International Languages','JAPN','Japanese','Assignment'),
('2021-22','Fall 2021','Fall','JAPN C185','24640','CSLO 1','Met','C00178434','Active','Elementary Japanese 2','Online','Recognize and produce the Japanese language at the advanced beginning level in the four primary areas of communication: listening, speaking, reading, and writing. 	 Describe and analyze significant differences in culture-specific behaviors between the cul','International Languages','JAPN','Japanese','Assignment'),
('2021-22','Fall 2021','Fall','JAPN C185','24640','CSLO 2','Met','C00178434','Active','Elementary Japanese 2','Online','Describe and analyze significant differences in culture-specific behaviors between the cultures of the Japanese-speaking world and the United States by identifying the culture in which the variant is practiced (personal space, non-verbal behavior, treatm','International Languages','JAPN','Japanese','Assignment'),
('2021-22','Fall 2021','Fall','JAPN C180','24495','CSLO 1','Met','C00021692','Active','Elementary Japanese 1','Online','Given oral or written input by a native or near-native speaker of Japanese, demonstrate oral/aural or written competency at the elementary level by communicating in comprehensible language to a (native/near-native) speaker on topics related to self, immed','International Languages','JAPN','Japanese','Assignment'),
('2021-22','Fall 2021','Fall','JAPN C180','24495','CSLO 2','Met','C00021692','Active','Elementary Japanese 1','Online','Demonstrate an emerging awareness of significant differences in culture-specific behaviors between the cultures of the Japanese language speakers and the United States to include, but not limited to, non-verbal behaviors and social expectations.','International Languages','JAPN','Japanese','Assignment'),
('2021-22','Fall 2021','Fall','JAPN C185','24640','CSLO 1','Met','C00232798','Active','Elementary Japanese 2','Online','Recognize and produce the Japanese language at the advanced beginning level in the four primary areas of communication: listening, speaking, reading, and writing. 	 Describe and analyze significant differences in culture-specific behaviors between the cul','International Languages','JAPN','Japanese','Assignment'),
('2021-22','Fall 2021','Fall','JAPN C185','24640','CSLO 2','Met','C00232798','Active','Elementary Japanese 2','Online','Describe and analyze significant differences in culture-specific behaviors between the cultures of the Japanese-speaking world and the United States by identifying the culture in which the variant is practiced (personal space, non-verbal behavior, treatm','International Languages','JAPN','Japanese','Assignment'),
('2021-22','Fall 2021','Fall','JAPN C185','24640','CSLO 1','Met','C00259067','Active','Elementary Japanese 2','Online','Recognize and produce the Japanese language at the advanced beginning level in the four primary areas of communication: listening, speaking, reading, and writing. 	 Describe and analyze significant differences in culture-specific behaviors between the cul','International Languages','JAPN','Japanese','Assignment'),
('2021-22','Fall 2021','Fall','JAPN C185','24640','CSLO 2','Met','C00259067','Active','Elementary Japanese 2','Online','Describe and analyze significant differences in culture-specific behaviors between the cultures of the Japanese-speaking world and the United States by identifying the culture in which the variant is practiced (personal space, non-verbal behavior, treatm','International Languages','JAPN','Japanese','Assignment'),
('2021-22','Fall 2021','Fall','JAPN C180','24495','CSLO 1','Not Met','C00278450','Active','Elementary Japanese 1','Online','Given oral or written input by a native or near-native speaker of Japanese, demonstrate oral/aural or written competency at the elementary level by communicating in comprehensible language to a (native/near-native) speaker on topics related to self, immed','International Languages','JAPN','Japanese','Assignment'),
('2021-22','Fall 2021','Fall','JAPN C180','24495','CSLO 2','Not Met','C00278450','Active','Elementary Japanese 1','Online','Demonstrate an emerging awareness of significant differences in culture-specific behaviors between the cultures of the Japanese language speakers and the United States to include, but not limited to, non-verbal behaviors and social expectations.','International Languages','JAPN','Japanese','Assignment'),
('2021-22','Fall 2021','Fall','JAPN C185','24640','CSLO 1','Met','C00302691','Active','Elementary Japanese 2','Online','Recognize and produce the Japanese language at the advanced beginning level in the four primary areas of communication: listening, speaking, reading, and writing. 	 Describe and analyze significant differences in culture-specific behaviors between the cul','International Languages','JAPN','Japanese','Assignment'),
('2023-24','Fall 2023','Fall','BIOL C220','11810','CSLO 2','Partially Met',NULL,'Active','Human Anatomy','Online','Follow appropriate laboratory etiquette and laboratory technique (including effective dissection and use of the compound light microscope).','Biological Sciences and Allied Health','BIOL','Biology','Assignment');

COMMIT;
SET foreign_key_checks=1;
SET unique_checks=1;
