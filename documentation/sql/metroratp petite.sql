-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : dim. 21 juin 2026 à 12:56
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `metroratp`
--

-- --------------------------------------------------------

--
-- Structure de la table `acces`
--

CREATE TABLE `acces` (
  `id` int(11) NOT NULL,
  `label` varchar(100) DEFAULT NULL,
  `numero` varchar(4) DEFAULT NULL,
  `is_accessible` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `acces`
--

INSERT INTO `acces` (`id`, `label`, `numero`, `is_accessible`) VALUES
(1, 'Grande Arche', '1', 1),
(2, 'Dôme', '2', 1),
(3, 'Boieldieu', '3', 1),
(4, 'Parvis', '4', 1),
(5, 'Calder-Miró', '5', 1),
(6, 'Coupole', '6', 1),
(7, 'Place Carpeaux', '7', 1),
(8, 'Salle des Colonnes', '8', 1),
(9, 'Esplanade de la Défense', '1', 1),
(10, 'Pont de Neuilly', '1', 1),
(11, 'Avenue de Madrid', '2', 0),
(12, 'Place du Marché', '3', 0),
(13, 'Louis Huet', '1', 0),
(14, 'Place du Marché', '2', 0),
(15, 'Avenue Charles de Gaulle', '1', 1),
(16, 'Place de la Porte Maillot', '2', 1),
(17, 'Palais des Congrès', '3', 1),
(18, 'Rue de Villiers', '4', 0),
(19, 'Avenue de la Grande Armée', '1', 0),
(20, 'Rue Argentine', '2', 0),
(21, 'Avenue des Champs-Élysées', '1', 0),
(22, 'Avenue de Friedland', '2', 0),
(23, 'Avenue de la Grande Armée', '3', 0),
(24, 'Avenue de Wagram', '4', 0),
(25, 'Avenue Carnot', '5', 0),
(26, 'Avenue des Champs-Élysées', '1', 0),
(27, 'Avenue de l\'Alma', '2', 0),
(28, 'Avenue des Champs-Élysées', '1', 0),
(29, 'Avenue Montaigne', '2', 0),
(30, 'Rond-point des Champs-Élysées', '3', 0),
(31, 'Place Clemenceau', '1', 0),
(32, 'Avenue de Selves', '2', 0),
(33, 'Place de la Concorde', '1', 0),
(34, 'Rue Royale', '2', 0),
(35, 'Rue de Rivoli', '3', 0),
(36, 'Rue de Rivoli', '1', 0),
(37, 'Place des Pyramides', '1', 0),
(38, 'Place du Palais Royal', '2', 0),
(39, 'Rue de Rivoli', '3', 0),
(40, 'Musée du Louvre', '4', 0),
(41, 'Rue de l\'Amiral de Coligny', '1', 0),
(42, 'Rue de Rivoli', '2', 0),
(43, 'Rue de Rivoli', '1', 0),
(44, 'Rue des Lavandières', '2', 0),
(45, 'Avenue Victoria', '4', 0),
(46, 'Place du Châtelet', '5', 0),
(47, 'Place de l\'Hôtel de Ville', '1', 0),
(48, 'Rue de Lobau', '2', 0),
(49, 'Rue de Rivoli', '1', 0),
(50, 'Boulevard Henri IV', '1', 0),
(51, 'Boulevard Richard Lenoir', '2', 0),
(52, 'Rue de Lyon', '3', 0),
(53, 'Boulevard de la Bastille', '4', 0),
(54, 'Opéra Bastille', '5', 0),
(55, 'Boulevard Diderot', '1', 1),
(56, 'Ministère de l\'Économie', '2', 1),
(57, 'Rue de Bercy', '3', 1),
(58, 'Rue de Reuilly', '1', 0),
(59, 'Boulevard Diderot', '2', 0),
(60, 'Avenue du Trône', '1', 0),
(61, 'Avenue Taillebourg', '2', 0),
(62, 'Avenue du Bel-Air', '3', 0),
(63, 'Boulevard Voltaire', '4', 0),
(64, 'Avenue de la République', '5', 0),
(65, 'Avenue du Général-Michel-Bizot', '1', 0),
(66, 'Avenue de la Porte-de-Vincennes', '2', 0),
(67, 'Avenue Joffre', '1', 0),
(68, 'Avenue du Général-de-Gaulle', '2', 0),
(69, 'Avenue du Château', '1', 0),
(70, 'Avenue de Paris', '1', 0),
(71, 'Cours Marigny', '2', 0);

-- --------------------------------------------------------

--
-- Structure de la table `ligne`
--

CREATE TABLE `ligne` (
  `id` int(11) NOT NULL,
  `label` varchar(5) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `ligne`
--

INSERT INTO `ligne` (`id`, `label`) VALUES
(1, '1'),
(2, '2'),
(3, '3'),
(4, '3b'),
(5, '4'),
(6, '5'),
(7, '6'),
(8, '7'),
(9, '7b'),
(10, '8'),
(11, '9'),
(12, '10'),
(13, '11'),
(14, '12'),
(15, '13'),
(16, '14'),
(17, '15'),
(18, '16');

-- --------------------------------------------------------

--
-- Structure de la table `materiel`
--

CREATE TABLE `materiel` (
  `id` int(11) NOT NULL,
  `label` varchar(50) DEFAULT NULL,
  `type_materiel_id` int(11) DEFAULT NULL,
  `annee_production` varchar(5) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `materiel`
--

INSERT INTO `materiel` (`id`, `label`, `type_materiel_id`, `annee_production`) VALUES
(1, 'M1', 2, '1900'),
(2, 'Sprague-Thomson', 2, '1908'),
(3, 'MA 51', 2, '1951'),
(4, 'MP 51', 1, '1951'),
(5, 'MP 55', 1, '1955'),
(6, 'MP 59', 1, '1959'),
(7, 'MF 67', 2, '1967'),
(8, 'MP 73', 1, '1973'),
(9, 'MF 77', 2, '1977'),
(10, 'MF 88', 2, '1988'),
(11, 'MP 89', 1, '1989'),
(12, 'MF 01', 2, '2001'),
(13, 'MP 05', 1, '2005'),
(14, 'MP 14', 1, '2014'),
(15, 'MF 19', 2, '2019');

-- --------------------------------------------------------

--
-- Structure de la table `materiel_ligne`
--

CREATE TABLE `materiel_ligne` (
  `id` int(11) NOT NULL,
  `materiel_id` int(11) DEFAULT NULL,
  `ligne_id` int(11) DEFAULT NULL,
  `arrivee` date DEFAULT NULL,
  `fin` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `materiel_ligne`
--

INSERT INTO `materiel_ligne` (`id`, `materiel_id`, `ligne_id`, `arrivee`, `fin`) VALUES
(1, 2, 1, '1908-01-01', '1963-12-31'),
(2, 7, 1, '1963-01-01', '2000-12-31'),
(3, 11, 1, '1998-01-01', '2012-12-31'),
(4, 14, 1, '2011-01-01', NULL),
(5, 2, 2, '1908-01-01', '1983-12-31'),
(6, 7, 2, '1974-01-01', '2011-12-31'),
(7, 15, 2, '2011-01-01', NULL),
(8, 2, 3, '1904-01-01', '1980-12-31'),
(9, 7, 3, '1975-01-01', '2020-12-31'),
(10, 15, 3, '2020-01-01', NULL),
(11, 2, 4, '1908-01-01', '1976-12-31'),
(12, 9, 4, '1976-01-01', '2012-12-31'),
(13, 15, 4, '2012-01-01', NULL),
(14, 2, 5, '1907-01-01', '1980-12-31'),
(15, 7, 5, '1970-01-01', '2010-12-31'),
(16, 12, 5, '2010-01-01', NULL),
(17, 2, 6, '1905-01-01', '1975-12-31'),
(18, 8, 6, '1974-01-01', '2008-12-31'),
(19, 13, 6, '2008-01-01', NULL),
(20, 2, 7, '1910-01-01', '1980-12-31'),
(21, 7, 7, '1975-01-01', '2011-12-31'),
(22, 12, 7, '2011-01-01', NULL),
(23, 2, 8, '1913-01-01', '1978-12-31'),
(24, 7, 8, '1978-01-01', '2011-12-31'),
(25, 12, 8, '2011-01-01', NULL),
(26, 2, 9, '1922-01-01', '1981-12-31'),
(27, 7, 9, '1980-01-01', '2020-12-31'),
(28, 12, 9, '2020-01-01', NULL),
(29, 2, 10, '1923-01-01', '1980-12-31'),
(30, 7, 10, '1975-01-01', '2022-12-31'),
(31, 15, 10, '2022-01-01', NULL),
(32, 2, 11, '1935-01-01', '1979-12-31'),
(33, 7, 11, '1979-01-01', '2021-12-31'),
(34, 12, 11, '2021-01-01', NULL),
(35, 2, 12, '1910-01-01', '1978-12-31'),
(36, 9, 12, '1978-01-01', '2014-12-31'),
(37, 15, 12, '2014-01-01', NULL),
(38, 2, 13, '1911-01-01', '1976-12-31'),
(39, 9, 13, '1976-01-01', '2017-12-31'),
(40, 15, 13, '2017-01-01', NULL),
(41, 11, 14, '1998-10-15', '2012-12-31'),
(42, 14, 14, '2012-01-01', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `sens`
--

CREATE TABLE `sens` (
  `id` int(11) NOT NULL,
  `label` varchar(25) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `sens`
--

INSERT INTO `sens` (`id`, `label`) VALUES
(1, 'Nord'),
(2, 'Nord-Est'),
(3, 'Est'),
(4, 'Sud-Est'),
(5, 'Sud'),
(6, 'Sud-Ouest'),
(7, 'Ouest'),
(8, 'Nord-Ouest');

-- --------------------------------------------------------

--
-- Structure de la table `service`
--

CREATE TABLE `service` (
  `id` int(11) NOT NULL,
  `label` varchar(25) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `service`
--

INSERT INTO `service` (`id`, `label`) VALUES
(1, 'Unique'),
(2, 'Bleu'),
(3, 'Jaune');

-- --------------------------------------------------------

--
-- Structure de la table `sortie`
--

CREATE TABLE `sortie` (
  `id` int(11) NOT NULL,
  `acces_id` int(11) DEFAULT NULL,
  `station_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `sortie`
--

INSERT INTO `sortie` (`id`, `acces_id`, `station_id`) VALUES
(1, 1, 1),
(2, 2, 1),
(3, 3, 1),
(4, 4, 1),
(5, 5, 1),
(6, 6, 1),
(7, 7, 1),
(8, 8, 1),
(9, 9, 2),
(10, 10, 3),
(11, 11, 3),
(12, 12, 3),
(13, 13, 4),
(14, 14, 4),
(15, 15, 5),
(16, 16, 5),
(17, 17, 5),
(18, 18, 5),
(19, 19, 6),
(20, 20, 6),
(21, 21, 7),
(22, 22, 7),
(23, 23, 7),
(24, 24, 7),
(25, 25, 7),
(26, 26, 8),
(27, 27, 8),
(28, 28, 9),
(29, 29, 9),
(30, 30, 9),
(31, 31, 10),
(32, 32, 10),
(33, 33, 11),
(34, 34, 11),
(35, 35, 11),
(36, 36, 12),
(37, 37, 13),
(38, 38, 13),
(39, 39, 13),
(40, 40, 13),
(41, 41, 14),
(42, 42, 14),
(43, 43, 15),
(44, 44, 15),
(45, 45, 15),
(46, 46, 15),
(47, 47, 16),
(48, 48, 16),
(49, 49, 17),
(50, 50, 18),
(51, 51, 18),
(52, 52, 18),
(53, 53, 18),
(54, 54, 18),
(55, 55, 19),
(56, 56, 19),
(57, 57, 19),
(58, 58, 20),
(59, 59, 20),
(60, 60, 21),
(61, 61, 21),
(62, 62, 21),
(63, 63, 21),
(64, 64, 21),
(65, 65, 22),
(66, 66, 22),
(67, 67, 23),
(68, 68, 23),
(69, 69, 24),
(70, 70, 25),
(71, 71, 25);

-- --------------------------------------------------------

--
-- Structure de la table `station`
--

CREATE TABLE `station` (
  `id` int(11) NOT NULL,
  `label` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `station`
--

INSERT INTO `station` (`id`, `label`) VALUES
(1, 'La Défense'),
(2, 'Esplanade de la Défense'),
(3, 'Pont de Neuilly'),
(4, 'Les Sablons'),
(5, 'Porte Maillot'),
(6, 'Argentine'),
(7, 'Charles de Gaulle — Étoile'),
(8, 'George V'),
(9, 'Franklin D. Roosevelt'),
(10, 'Champs-Élysées — Clemenceau'),
(11, 'Concorde'),
(12, 'Tuileries'),
(13, 'Palais Royal — Musée du Louvre'),
(14, 'Louvre — Rivoli'),
(15, 'Châtelet'),
(16, 'Hôtel de Ville'),
(17, 'Saint-Paul'),
(18, 'Bastille'),
(19, 'Gare de Lyon'),
(20, 'Reuilly — Diderot'),
(21, 'Nation'),
(22, 'Porte de Vincennes'),
(23, 'Saint-Mandé'),
(24, 'Bérault'),
(25, 'Château de Vincennes'),
(26, 'Porte Dauphine'),
(27, 'Victor Hugo'),
(28, 'Ternes'),
(29, 'Courcelles'),
(30, 'Monceau'),
(31, 'Villiers'),
(32, 'Rome'),
(33, 'Place de Clichy'),
(34, 'Blanche'),
(35, 'Pigalle'),
(36, 'Anvers'),
(37, 'Barbès — Rochechouart'),
(38, 'La Chapelle'),
(39, 'Stalingrad'),
(40, 'Jaurès'),
(41, 'Colonel Fabien'),
(42, 'Belleville'),
(43, 'Couronnes'),
(44, 'Ménilmontant'),
(45, 'Père Lachaise'),
(46, 'Philippe Auguste'),
(47, 'Alexandre Dumas'),
(48, 'Avron'),
(49, 'Pont de Levallois — Bécon'),
(50, 'Anatole France'),
(51, 'Louise Michel'),
(52, 'Porte de Champerret'),
(53, 'Pereire'),
(54, 'Wagram'),
(55, 'Malesherbes'),
(56, 'Europe'),
(57, 'Saint-Lazare'),
(58, 'Havre — Caumartin'),
(59, 'Opéra'),
(60, 'Quatre-Septembre'),
(61, 'Bourse'),
(62, 'Sentier'),
(63, 'Réaumur — Sébastopol'),
(64, 'Arts et Métiers'),
(65, 'Temple'),
(66, 'République'),
(67, 'Parmentier'),
(68, 'Rue Saint-Maur'),
(69, 'Gambetta'),
(70, 'Porte de Bagnolet'),
(71, 'Gallieni'),
(72, 'Porte de Clignancourt'),
(73, 'Simplon'),
(74, 'Marcadet — Poissonniers'),
(75, 'Château Rouge'),
(76, 'Gare du Nord'),
(77, 'Gare de l\'Est'),
(78, 'Château d\'Eau'),
(79, 'Strasbourg — Saint-Denis'),
(80, 'Étienne Marcel'),
(81, 'Les Halles'),
(82, 'Cité'),
(83, 'Saint-Michel'),
(84, 'Odéon'),
(85, 'Saint-Germain-des-Prés'),
(86, 'Saint-Sulpice'),
(87, 'Saint-Placide'),
(88, 'Montparnasse — Bienvenüe'),
(89, 'Vavin'),
(90, 'Raspail'),
(91, 'Denfert-Rochereau'),
(92, 'Mouton-Duvernet'),
(93, 'Alésia'),
(94, 'Porte d\'Orléans'),
(95, 'Mairie de Montrouge'),
(96, 'Barbara'),
(97, 'Bagneux — Lucie Aubrac'),
(98, 'Bobigny — Pablo Picasso'),
(99, 'Bobigny — Pantin — Raymond Queneau'),
(100, 'Église de Pantin'),
(101, 'Hoche'),
(102, 'Porte de Pantin'),
(103, 'Ourcq'),
(104, 'Laumière'),
(105, 'Jacques Bonsergent'),
(106, 'Oberkampf'),
(107, 'Richard-Lenoir'),
(108, 'Bréguet — Sabin'),
(109, 'Quai de la Rapée'),
(110, 'Gare d\'Austerlitz'),
(111, 'Saint-Marcel'),
(112, 'Campo-Formio'),
(113, 'Place d\'Italie'),
(114, 'Kléber'),
(115, 'Boissière'),
(116, 'Trocadéro'),
(117, 'Passy'),
(118, 'Bir-Hakeim'),
(119, 'Dupleix'),
(120, 'La Motte-Picquet — Grenelle'),
(121, 'Cambronne'),
(122, 'Sèvres — Lecourbe'),
(123, 'Pasteur'),
(124, 'Edgar Quinet'),
(125, 'Saint-Jacques'),
(126, 'Glacière'),
(127, 'Corvisart'),
(128, 'Nationale'),
(129, 'Chevaleret'),
(130, 'Quai de la Gare'),
(131, 'Bercy'),
(132, 'Dugommier'),
(133, 'Daumesnil'),
(134, 'Bel-Air'),
(135, 'Picpus'),
(136, 'La Courneuve — 8 Mai 1945'),
(137, 'Fort d\'Aubervilliers'),
(138, 'Aubervilliers — Pantin — Quatre Chemins'),
(139, 'Porte de la Villette'),
(140, 'Corentin Cariou'),
(141, 'Crimée'),
(142, 'Riquet'),
(143, 'Louis Blanc'),
(144, 'Château-Landon'),
(145, 'Poissonnière'),
(146, 'Cadet'),
(147, 'Le Peletier'),
(148, 'Chaussée d\'Antin — La Fayette'),
(149, 'Pyramides'),
(150, 'Pont Neuf'),
(151, 'Pont Marie'),
(152, 'Sully — Morland'),
(153, 'Jussieu'),
(154, 'Place Monge'),
(155, 'Censier — Daubenton'),
(156, 'Les Gobelins'),
(157, 'Tolbiac'),
(158, 'Maison Blanche'),
(159, 'Le Kremlin-Bicêtre'),
(160, 'Villejuif — Léo Lagrange'),
(161, 'Villejuif — Paul Vaillant-Couturier'),
(162, 'Villejuif — Louis Aragon'),
(163, 'Porte d\'Italie'),
(164, 'Porte de Choisy'),
(165, 'Porte d\'Ivry'),
(166, 'Pierre et Marie Curie'),
(167, 'Mairie d\'Ivry'),
(168, 'Balard'),
(169, 'Lourmel'),
(170, 'Boucicaut'),
(171, 'Félix Faure'),
(172, 'Commerce'),
(173, 'La Motte-Picquet — Grenelle'),
(174, 'École Militaire'),
(175, 'La Tour-Maubourg'),
(176, 'Invalides'),
(177, 'Madeleine'),
(178, 'Richelieu — Drouot'),
(179, 'Grands Boulevards'),
(180, 'Bonne Nouvelle'),
(181, 'Filles du Calvaire'),
(182, 'Saint-Sébastien — Froissart'),
(183, 'Chemin Vert'),
(184, 'Ledru-Rollin'),
(185, 'Faidherbe — Chaligny'),
(186, 'Montgallet'),
(187, 'Michel Bizot'),
(188, 'Porte Dorée'),
(189, 'Porte de Charenton'),
(190, 'Liberté'),
(191, 'Charenton — Écoles'),
(192, 'École Vétérinaire de Maisons-Alfort'),
(193, 'Maisons-Alfort — Stade'),
(194, 'Maisons-Alfort — Les Juilliottes'),
(195, 'Créteil — L\'Échat'),
(196, 'Créteil — Université'),
(197, 'Créteil — Préfecture'),
(198, 'Pointe du Lac'),
(199, 'Pont de Sèvres'),
(200, 'Billancourt'),
(201, 'Marcel Sembat'),
(202, 'Porte de Saint-Cloud'),
(203, 'Exelmans'),
(204, 'Michel-Ange — Molitor'),
(205, 'Michel-Ange — Auteuil'),
(206, 'Jasmin'),
(207, 'Ranelagh'),
(208, 'La Muette'),
(209, 'Rue de la Pompe'),
(210, 'Iéna'),
(211, 'Alma — Marceau'),
(212, 'Saint-Philippe du Roule'),
(213, 'Miromesnil'),
(214, 'Saint-Augustin'),
(215, 'Saint-Ambroise'),
(216, 'Voltaire'),
(217, 'Charonne'),
(218, 'Rue des Boulets'),
(219, 'Buzenval'),
(220, 'Maraîchers'),
(221, 'Porte de Montreuil'),
(222, 'Robespierre'),
(223, 'Croix de Chavaux'),
(224, 'Mairie de Montreuil'),
(225, 'Boulogne — Pont de Saint-Cloud'),
(226, 'Boulogne — Jean Jaurès'),
(227, 'Porte d\'Auteuil'),
(228, 'Église d\'Auteuil'),
(229, 'Mirabeau'),
(230, 'Chardon-Lagache'),
(231, 'Javel - Parc André Citroën'),
(232, 'Charles Michels'),
(233, 'Avenue Émile Zola'),
(234, 'Ségur'),
(235, 'Duroc'),
(236, 'Vaneau'),
(237, 'Sèvres — Babylone'),
(238, 'Mabillon'),
(239, 'Cluny — La Sorbonne'),
(240, 'Maubert — Mutualité'),
(241, 'Cardinal Lemoine'),
(242, 'Rambuteau'),
(243, 'Goncourt'),
(244, 'Pyrénées'),
(245, 'Jourdain'),
(246, 'Place des Fêtes'),
(247, 'Télégraphe'),
(248, 'Porte des Lilas'),
(249, 'Mairie des Lilas'),
(250, 'Serge Gainsbourg'),
(251, 'Romainville — Carnot'),
(252, 'Montreuil — Hôpital'),
(253, 'La Dhuys'),
(254, 'Coteaux Beauclair'),
(255, 'Rosny-Bois-Perrier'),
(256, 'Mairie d\'Aubervilliers'),
(257, 'Aimé Césaire'),
(258, 'Front Populaire'),
(259, 'Porte de la Chapelle'),
(260, 'Marx Dormoy'),
(261, 'Jules Joffrin'),
(262, 'Lamarck — Caulaincourt'),
(263, 'Abbesses'),
(264, 'Saint-Georges'),
(265, 'Notre-Dame-de-Lorette'),
(266, 'Trinité — d\'Estienne d\'Orves'),
(267, 'Assemblée Nationale'),
(268, 'Solférino'),
(269, 'Rue du Bac'),
(270, 'Rennes'),
(271, 'Notre-Dame-des-Champs'),
(272, 'Falguière'),
(273, 'Volontaires'),
(274, 'Vaugirard'),
(275, 'Convention'),
(276, 'Porte de Versailles'),
(277, 'Corentin Celton'),
(278, 'Mairie d\'Issy'),
(279, 'Les Courtilles'),
(280, 'Les Agnettes'),
(281, 'Gabriel Péri'),
(282, 'Mairie de Clichy'),
(283, 'Porte de Clichy'),
(284, 'Brochant'),
(285, 'Saint-Denis — Université'),
(286, 'Basilique de Saint-Denis'),
(287, 'Saint-Denis — Porte de Paris'),
(288, 'Carrefour Pleyel'),
(289, 'Mairie de Saint-Ouen'),
(290, 'Garibaldi'),
(291, 'Porte de Saint-Ouen'),
(292, 'Guy Môquet'),
(293, 'La Fourche'),
(294, 'Liège'),
(295, 'Varenne'),
(296, 'Saint-François-Xavier'),
(297, 'Gaîté'),
(298, 'Pernety'),
(299, 'Plaisance'),
(300, 'Porte de Vanves'),
(301, 'Malakoff — Plateau de Vanves'),
(302, 'Malakoff — Rue Étienne Dolet'),
(303, 'Châtillon — Montrouge'),
(304, 'Saint-Denis — Pleyel'),
(305, 'Saint-Ouen'),
(306, 'Pont Cardinet'),
(307, 'Cour Saint-Émilion'),
(308, 'Bibliothèque François Mitterrand'),
(309, 'Olympiades'),
(310, 'Hôpital Bicêtre'),
(311, 'Villejuif — Gustave Roussy'),
(312, 'L\'Haÿ-les-Roses'),
(313, 'Chevilly-Larue'),
(314, 'Thiais — Orly'),
(315, 'Aéroport d\'Orly');

-- --------------------------------------------------------

--
-- Structure de la table `style_station`
--

CREATE TABLE `style_station` (
  `id` int(11) NOT NULL,
  `label` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `style_station`
--

INSERT INTO `style_station` (`id`, `label`) VALUES
(1, 'mouton'),
(2, 'motte'),
(3, 'renouveau du métro'),
(4, 'CMP'),
(5, 'Nord Sud');

-- --------------------------------------------------------

--
-- Structure de la table `type_materiel`
--

CREATE TABLE `type_materiel` (
  `id` int(11) NOT NULL,
  `label` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `type_materiel`
--

INSERT INTO `type_materiel` (`id`, `label`) VALUES
(1, 'pneumatique'),
(2, 'ferraille');

-- --------------------------------------------------------

--
-- Structure de la table `type_troncon`
--

CREATE TABLE `type_troncon` (
  `id` int(11) NOT NULL,
  `label` varchar(25) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `type_troncon`
--

INSERT INTO `type_troncon` (`id`, `label`) VALUES
(1, 'Interieur'),
(2, 'Exterieur'),
(3, 'Semi'),
(4, 'Transition');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `acces`
--
ALTER TABLE `acces`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `ligne`
--
ALTER TABLE `ligne`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `materiel`
--
ALTER TABLE `materiel`
  ADD PRIMARY KEY (`id`),
  ADD KEY `materiel_type_materiel` (`type_materiel_id`);

--
-- Index pour la table `materiel_ligne`
--
ALTER TABLE `materiel_ligne`
  ADD PRIMARY KEY (`id`),
  ADD KEY `materiel_ligne_materiel` (`materiel_id`),
  ADD KEY `materiel_ligne_ligne` (`ligne_id`);

--
-- Index pour la table `sens`
--
ALTER TABLE `sens`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `service`
--
ALTER TABLE `service`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `sortie`
--
ALTER TABLE `sortie`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sortie_acces` (`acces_id`),
  ADD KEY `sortie_station` (`station_id`);

--
-- Index pour la table `station`
--
ALTER TABLE `station`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `style_station`
--
ALTER TABLE `style_station`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `type_materiel`
--
ALTER TABLE `type_materiel`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `type_troncon`
--
ALTER TABLE `type_troncon`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `acces`
--
ALTER TABLE `acces`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT pour la table `ligne`
--
ALTER TABLE `ligne`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT pour la table `materiel`
--
ALTER TABLE `materiel`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT pour la table `materiel_ligne`
--
ALTER TABLE `materiel_ligne`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT pour la table `sens`
--
ALTER TABLE `sens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `service`
--
ALTER TABLE `service`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `sortie`
--
ALTER TABLE `sortie`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT pour la table `station`
--
ALTER TABLE `station`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=316;

--
-- AUTO_INCREMENT pour la table `style_station`
--
ALTER TABLE `style_station`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `type_materiel`
--
ALTER TABLE `type_materiel`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `type_troncon`
--
ALTER TABLE `type_troncon`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `materiel`
--
ALTER TABLE `materiel`
  ADD CONSTRAINT `materiel_type_materiel` FOREIGN KEY (`type_materiel_id`) REFERENCES `type_materiel` (`id`);

--
-- Contraintes pour la table `materiel_ligne`
--
ALTER TABLE `materiel_ligne`
  ADD CONSTRAINT `materiel_ligne_ligne` FOREIGN KEY (`ligne_id`) REFERENCES `ligne` (`id`),
  ADD CONSTRAINT `materiel_ligne_materiel` FOREIGN KEY (`materiel_id`) REFERENCES `materiel` (`id`);

--
-- Contraintes pour la table `sortie`
--
ALTER TABLE `sortie`
  ADD CONSTRAINT `sortie_acces` FOREIGN KEY (`acces_id`) REFERENCES `acces` (`id`),
  ADD CONSTRAINT `sortie_station` FOREIGN KEY (`station_id`) REFERENCES `station` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
