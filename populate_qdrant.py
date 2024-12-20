from qdrant_service import add_document

lethabo_data = [
    "Lethabo Sekoto is a Full Stack & AI Developer specializing in creating intelligent web applications.",
    "Lethabo has experience with JavaScript, including React, Vue.js, and Node.js.",
    "Lethabo's Python skills include working with Django and TensorFlow for AI applications.",
    "Lethabo has developed mobile applications using React Native.",
    "Lethabo is proficient in both SQL and NoSQL database management.",
    "Lethabo has experience with DevOps and cloud services, particularly AWS and Docker.",
    "One of Lethabo's key projects is an AI-powered chat application that includes sentiment analysis and language translation.",
    "Lethabo has also built a full-featured e-commerce platform with product recommendations and inventory management.",
    "Another significant project by Lethabo is a mobile fitness tracker with AI-powered workout recommendations.",
    "Lethabo has worked as a Senior Full Stack Developer at TechCorp Inc., leading high-traffic web application development.",
    "Prior to that, Lethabo was an AI Engineer at InnovateAI, developing machine learning models for NLP and computer vision."
]

for data in lethabo_data:
    result = add_document(data)
    print(result)

print("Data population complete.")