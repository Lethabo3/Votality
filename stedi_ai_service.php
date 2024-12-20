<?php
require_once 'stedi_ai_config.php';
require_once 'logging.php';

class StediAIService {
    private $apiKey;
    private $apiUrl;
    private $conversationHistory = [];
    private $portfolioData;

    public function __construct() {
        $this->apiKey = GEMINI_API_KEY;
        $this->apiUrl = GEMINI_API_URL;
        $this->portfolioData = $this->getPortfolioData();
    }

    private function getPortfolioData() {
        return [
            "name" => "Lethabo Sekoto",
            "personal_information" => [
                "date_of_birth" => "2005-12-04",
                "place_of_birth" => "Limpopo, South Africa",
                "age" => 19,
                "citizenship" => "South African",
                "nationality" => "South African",
                "cultural_background" => "Pedi (Northern Sotho)",
                "languages" => [
                    "English" => "Fluent (Most frequently used)",
                    "Sepedi" => "Fluent",
                    "Zulu" => "Fluent",
                    "German" => "Basic"
                ]
            ],
            "contact_information" => [
                "email" => "lethabosekoto@drivestedi.com",
                "linkedin" => "https://za.linkedin.com/in/lethabo-sekoto-0549081b2",
                "github" => "https://github.com/Lethabo3",
                "location" => [
                    "residence" => "Midrand, Gauteng",
                    "study" => "Pretoria, Gauteng"
                ]
            ],
            "role" => "Full Stack Developer, Entrepreneur",
            "specialization" => "Creating innovative IT solutions with a focus on road safety and small business growth",
            "personal_statement" => "My motto is 'learning through creation, build and learn'. I'm passionate about using technology to improve lives and make the world safer.",
            "background" => [
                "Started coding at age 14",
                "Obtained certificates in HTML and CSS at a young age",
                "Studied IT in high school for 4 years",
                "Learned various topics including OOP, Delphi, databases, and SQL"
            ],
            "education" => [
                "current" => [
                    "institution" => "Belgium Campus ITversity",
                    "location" => "Pretoria, Gauteng",
                    "program" => "Bachelor's in Computing (Honours degree)",
                    "duration" => "4-year program",
                    "focus" => "Software Engineering",
                    "expected_graduation" => "2027"
                ],
                "additional_certifications" => $this->getCertificatesHTML()
            ],
            "skills" => [
                "Full Stack Web Development",
                "Front-end Development (HTML, CSS, JavaScript)",
                "Back-end Development",
                "Database Management (SQL, NoSQL)",
                "OOP concepts",
                "Delphi",
                "AI and Machine Learning",
                "Project Management",
                "Cybersecurity"
            ],
            "technical_proficiencies" => [
                "Languages" => ["HTML", "CSS", "JavaScript", "SQL"],
                "Frameworks" => ["Proficient in building applications without relying on specific frameworks"],
                "Concepts" => ["OOP", "Full Stack Development", "Database Management", "AI and Machine Learning"],
                "Tools" => ["Experienced in using various development tools and environments"]
            ],
            "projects" => [
                [
                    "name" => "Stedi",
                    "type" => "Startup company",
                    "role" => "Founder",
                    "duration" => "2 years (ongoing)",
                    "description" => "An app/company focusing on road safety and growing small businesses across Africa",
                    "goals" => [
                        "Improve road safety in Africa",
                        "Support the growth of small businesses",
                        "Contribute to economic development across the continent"
                    ],
                    "technologies" => [
                        "Full stack development using custom solutions",
                        "Database management for user data and analytics",
                        "Implementing AI algorithms for safety predictions and business insights"
                    ],
                    "website" => "https://www.drivestedi.com"
                ]
            ],
            "work_experience" => [
                "2 years of entrepreneurial experience building Stedi",
                "Developed and maintained full-stack applications for Stedi"
            ],
            "soft_skills" => [
                "Team player",
                "Positive and energetic",
                "Fast learner",
                "Effective communicator",
                "Composed under pressure",
                "Adaptable"
            ],
            "characteristics" => [
                "Ambitious",
                "Self-motivated learner",
                "Passionate about technology",
                "Aims to create projects that better Africa and the world"
            ],
            "career_goals" => [
                "Short-term: Successfully navigate the challenges of starting and growing companies",
                "Short-term: Gain recognition in the tech industry",
                "Long-term: Contribute to making society safer for everyone",
                "Long-term: Improve the education system",
                "Long-term: Help struggling individuals through technological innovations"
            ],
            "interests" => [
                "Coding and software development",
                "Learning about startup companies",
                "Psychology",
                "Biology",
                "Physics",
                "History",
                "Reading",
                "Taking walks",
                "Going to the gym",
                "Listening to music",
                "Playing video games (occasionally)",
                "Spending time with family",
                "Playing with my dog",
                "Collecting rare coins",
                "Watching football (Chelsea fan)"
            ],
            "personal_achievements" => [
                "Learned kickboxing",
                "Developed unique physical flexibility",
                "Built a collection of rare coins"
            ],
            "inspirations" => [
                "Mother's hard work and dedication",
                "Elon Musk",
                "Mark Zuckerberg",
                "Achilles",
                "Napoleon"
            ],
            "favorite_book" => "The Dictator's Handbook by Bruce Bueno de Mesquita and Alastair Smith",
            "travel_experience" => [
                "Visited Swaziland"
            ],
            "unique_skills" => [
                "Kickboxing",
                "Singing",
                "Exceptional physical flexibility"
            ],
            "formative_experiences" => [
                "Mother's hard work and dedication as inspiration",
                "Personal experience with a road accident, inspiring work on Stedi",
                "Influence of movies like The Matrix and hacker films in fostering interest in technology"
            ],
            "vision_for_africa" => "To see Africa become more connected, with all countries working together towards technological advancement and improved quality of life",
            "availability" => "Open to collaboration opportunities and projects in the Gauteng area, interested in both established companies and developing startups",
            "references" => "Available upon request"
        ];
    }

    private function getCertificatesHTML() {
        $certificates = [
            ["name" => "Software Engineering Specialization", "issuer" => "University of Hong Kong", "file" => "path/to/hongkong_cert.jpg"],
            ["name" => "AI Engineering Certificate", "issuer" => "IBM", "file" => "path/to/ibm_ai_cert.jpg"],
            ["name" => "AI Developer Certificate", "issuer" => "IBM", "file" => "path/to/ibm_dev_cert.jpg"],
            ["name" => "Web Development Certificate", "issuer" => "Google", "file" => "path/to/google_web_cert.jpg"],
            ["name" => "Project Management Certificate", "issuer" => "Google", "file" => "path/to/google_pm_cert.jpg"],
            ["name" => "Cybersecurity Certificate", "issuer" => "Google", "file" => "path/to/google_cyber_cert.jpg"]
        ];

        $html = '<div class="certificates-container">';
        foreach ($certificates as $cert) {
            $html .= $this->createCertificateBentoBox($cert);
        }
        $html .= '</div>';

        return $html;
    }

    private function createCertificateBentoBox($certificate) {
        return "
            <div class='project-bento-box certificate-box' onclick='openCertificateImage(\"{$certificate['file']}\")'>
                <div class='project-bento-content'>
                    <img src='{$certificate['file']}' alt='{$certificate['name']}' class='project-image'>
                    <div class='project-info'>
                        <h3 class='project-title'>{$certificate['name']}</h3>
                        <p class='certificate-description'>Issued by {$certificate['issuer']}</p>
                    </div>
                    <svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='currentColor' width='24' height='24'>
                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M14 5l7 7m0 0l-7 7m7-7H3' />
                    </svg>
                </div>
            </div>
        ";
    }

    public function generateResponse($message, $chatId) {
        logMessage("Generating response for message: " . $message, 'ai_service_debug.txt');
    
        $this->addToHistory('user', $message);
    
        try {
            $context = $this->prepareContext();
            $instructions = $this->prepareInstructions();
            logMessage("Prepared context: " . json_encode($context), 'ai_service_debug.txt');
    
            $conversationContent = $this->prepareConversationContent($context, $instructions);
    
            $data = json_encode([
                'contents' => $conversationContent,
                'generationConfig' => [
                    'temperature' => 0.7,
                    'topK' => 40,
                    'topP' => 0.95,
                    'maxOutputTokens' => 1024,
                ]
            ]);
    
            logMessage("Sending request to API with data: " . $data, 'ai_service_debug.txt');
    
            $ch = curl_init($this->apiUrl . '?key=' . $this->apiKey);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
    
            $response = curl_exec($ch);
            $error = curl_error($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);
    
            logMessage("API Response Code: " . $info['http_code'], 'ai_service_debug.txt');
            logMessage("API Response: " . $response, 'ai_service_debug.txt');
    
            if ($error) {
                throw new Exception("cURL Error: $error");
            }
    
            if ($info['http_code'] != 200) {
                throw new Exception("HTTP Error: " . $info['http_code'] . "\nResponse: " . $response);
            }
    
            $result = json_decode($response, true);
    
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("JSON Decode Error: " . json_last_error_msg() . "\nRaw Response: " . $response);
            }
    
            if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                $aiResponse = $result['candidates'][0]['content']['parts'][0]['text'];
                $cleanedResponse = $this->removeAsterisks($aiResponse);
                
                $this->addToHistory('ai', $cleanedResponse);
                
                logMessage("Cleaned AI Response: " . $cleanedResponse, 'ai_service_debug.txt');
                return $cleanedResponse;
            } else {
                throw new Exception("Unexpected API response structure: " . print_r($result, true));
            }
        } catch (Exception $e) {
            logMessage("Error in generateResponse: " . $e->getMessage(), 'ai_service_debug.txt');
            return "I apologize, but I encountered an error while processing your request: " . $e->getMessage();
        }
    }

    private function removeAsterisks($text) {
        $text = preg_replace('/\*+/', '', $text);
        return trim($text);
    }

    private function addToHistory($role, $message) {
        $this->conversationHistory[] = ['role' => $role, 'content' => $message];
        if (count($this->conversationHistory) > 10) {
            array_shift($this->conversationHistory);
        }
    }

    private function prepareContext() {
        return json_encode($this->portfolioData);
    }

    private function prepareInstructions() {
        return "You are an AI assistant for Lethabo Sekoto's portfolio website. Respond to questions about Lethabo based on the provided information. Keep your responses concise and directly related to the question asked. Highlight Lethabo's skills as a full-stack developer, his entrepreneurial experience with Stedi, his current studies, and his passion for using technology to improve Africa. Emphasize his proficiency in building full-stack applications without relying on specific frameworks. Mention his interests in psychology, biology, physics, history, collecting rare coins, and his love for football (especially Chelsea) when relevant. Highlight his personal motto of 'learning through creation, build and learn'. When appropriate, mention his unique skills like kickboxing and physical flexibility. Emphasize his vision for a more connected Africa and his goals to improve road safety and education. If asked for contact information, provide his email address. When discussing certificates or qualifications, you can mention that they are displayed in an interactive format on the website. If asked about something not in the provided information, politely say you don't have that specific information.";
    }

    private function prepareConversationContent($context, $instructions) {
        $content = [
            [
                'role' => 'user',
                'parts' => [['text' => $instructions . "\n\nLethabo's information: " . $context]]
            ]
        ];
        foreach ($this->conversationHistory as $entry) {
            $role = $entry['role'] === 'ai' ? 'model' : 'user';
            $content[] = [
                'role' => $role,
                'parts' => [['text' => $entry['content']]]
            ];
        }
        return $content;
    }
}